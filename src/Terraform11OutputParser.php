<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

use SK\TerraformParser\Change\AttributeChange;
use SK\TerraformParser\Change\ResourceChange;
use SK\TerraformParser\Parsers\Terraform11AttributeParser;
use SK\TerraformParser\Parsers\Terraform11ResourceParser;

class Terraform11OutputParser implements TerraformPlanParserInterface
{
    use ErrorHandlingTrait;

    const NO_CHANGES_STRING = "\nNo changes. Infrastructure is up-to-date.\n";
    const CONTENT_START_STRING = "\nTerraform will perform the following actions:\n";
    const CONTENT_END_STRING = "\nPlan:";

    const MODULES_START_STRING = "Initializing modules...\n";
    const MODULES_END_STRING = "\nInitializing provider plugins...";
    const MODULE_NAME_REGEX = '/^(module\.(?:.+))/';
    const MODULE_SOURCE_REGEX = '/^Getting source "(.+)"/';
    const MODULE_VERSION_REGEX = '/\?ref\=([^\s]+)/';

    const PRIMARY_MODULE_START_STRING = "\nCopying configuration from ";
    const PRIMARY_MODULE_REGEX = '/^Copying configuration from "(.+)"/';

    const ATTRIBUTE_LINE_REGEX = '/^ {6}.+[:]{1}/';
    const ATTRIBUTE_FORCES_NEW_RESOURCE_SUFFIX = ' (forces new resource)';

    const ERR_START_NOT_FOUND = 'Could not find beginning of plan output.';
    const ERR_END_NOT_FOUND = 'Could not find end of plan output.';

    const ERR_INVALID_RESOURCE = 'Failed to parse resource name (line %s)';
    const ERR_INVALID_ATTRIBUTE = 'Failed to parse attribute (line %s)';

    /**
     * @var Terraform11ResourceParser
     */
    private $resourceParser;

    /**
     * @var Terraform11AttributeParser
     */
    private $attributeParser;

    /**
     * @param Terraform11ResourceParser $resourceParser
     * @param Terraform11AttributeParser $attributeParser
     */
    public function __construct(
        ?Terraform11ResourceParser $resourceParser = null,
        ?Terraform11AttributeParser $attributeParser = null
    ) {
        $this->resourceParser = $resourceParser ?: new Terraform11ResourceParser;
        $this->attributeParser = $attributeParser ?: new Terraform11AttributeParser;
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    public function parseFile(string $filename): array
    {
        $content = file_get_contents($filename);
        return $this->parse($content);
    }

    /**
     * @param string $input
     *
     * @return array
     */
    public function parse(string $input): array
    {
        $this->resetErrors();

        $input = $this->stripANSICodes($input);
        $input = $this->sanitizeWindowsLineEndings($input);

        if (strpos($input, self::NO_CHANGES_STRING) !== false) {
            return [
                'errors' => $this->errors(),
                'changedResources' => [],
                'modules' => [],
            ];
        }

        $changes = [];
        $modules = [];

        $output = $this->getChangeOutput($input);
        if ($output !== null) {
            $changes = $this->parseChanges($output);
        }

        $output = $this->getModulesOutput($input);
        if ($output !== null) {
            $modules = $this->parseUsedModules($output);
        }

        $output = $this->getPrimaryModulesOutput($input);
        if ($output !== null && ($primary = $this->parsePrimaryModule($output))) {
            $modules[] = $primary;
        }

        return [
            'errors' => $this->errors(),
            'changedResources' => $changes,
            'modules' => $modules,
        ];
    }

    /**
     * @param string $input
     *
     * @return string|null
     */
    private function getChangeOutput($input)
    {
        $begin = strpos($input, self::CONTENT_START_STRING);
        if ($begin !== false) {
            $input = substr($input, $begin + strlen(self::CONTENT_START_STRING));
        } else {
            $this->addError(self::ERR_START_NOT_FOUND);
            return null;
        }

        $end = strpos($input, self::CONTENT_END_STRING);
        if ($end !== false) {
            $input = substr($input, 0, $end);
        } else {
            $this->addError(self::ERR_END_NOT_FOUND);
            return null;
        }

        return $input;
    }

    /**
     * @param string $input
     *
     * @return string|null
     */
    private function getModulesOutput($input)
    {
        $begin = strpos($input, self::MODULES_START_STRING);
        if ($begin !== false) {
            $input = substr($input, $begin + strlen(self::MODULES_START_STRING));
        } else {
            return '';
        }

        $end = strpos($input, self::MODULES_END_STRING);
        if ($end !== false) {
            $input = substr($input, 0, $end);
        } else {
            return '';
        }

        return $input;
    }

    /**
     * @param string $input
     *
     * @return string|null
     */
    private function getPrimaryModulesOutput($input)
    {
        $begin = strpos($input, self::PRIMARY_MODULE_START_STRING);
        if ($begin !== false) {
            $input = substr($input, $begin);
        } else {
            return '';
        }

        $end = strpos($input, self::MODULES_END_STRING);
        if ($end !== false) {
            $input = substr($input, 0, $end);
        } else {
            return '';
        }

        return $input;
    }

    /**
     * @param string $input
     *
     * @return array
     */
    private function parseChanges($input)
    {
        $lastChange = null;
        $resources = [];

        $lines = explode("\n", $input);
        foreach ($lines as $lineNumber => $original) {
            $line = trim($original);

            // empty line delineates resources. Save the recorded resource and reset.
            if (strlen($line) === 0) {
                if ($lastChange) {
                    $resources[] = $lastChange;
                }

                // Reset ongoing counters
                $lastChange = null;
                continue;
            }

            $resourceChange = $this->resourceParser->parseLineForResource($line);

            if ($resourceChange) {
                // This is the header of a resource
                $lastChange = $resourceChange;

            } elseif ($this->resourceParser->hasErrors()) {
                $this->addError(sprintf(self::ERR_INVALID_RESOURCE, $lineNumber));

            } elseif (preg_match(self::ATTRIBUTE_LINE_REGEX, $original) === 1) {
                // This is an attribute for a resource
                if ($lastChange && ($attribute = $this->attributeParser->parseLineForAttribute($line))) {
                    $this->addChangedAttribute($lastChange, $attribute);

                    if ($this->attributeParser->hasErrors()) {
                        foreach ($this->attributeParser->errors() as $error) {
                            $this->addError($error);
                        }
                    }

                } else {
                    $this->addError(sprintf(self::ERR_INVALID_ATTRIBUTE, $lineNumber));
                }

            } else {
                $this->addError(sprintf(self::ERR_INVALID_RESOURCE, $lineNumber));
            }
        }

        // If there is an unrecorded change, record it.
        if ($lastChange) {
            $resources[] = $lastChange;
        }

        return $resources;
    }

    /**
     * @param string $input
     *
     * @return array
     */
    private function parseUsedModules($input)
    {
        if (!$input) {
            return [];
        }

        $modules = [];

        $lines = explode("\n", $input);
        foreach ($lines as $lineNumber => $original) {
            $line = trim($original);

            if (strpos($line, '- module.') === 0) {
                $next = $lines[$lineNumber + 1];
                $next = trim($next);
                if (strpos($next, 'Getting source ') === 0) {
                    $line = substr($line, 2);
                    if (
                        preg_match(self::MODULE_NAME_REGEX, $line, $matches1) === 1 &&
                        preg_match(self::MODULE_SOURCE_REGEX, $next, $matches2) === 1
                    ) {
                        $module = array_pop($matches1);
                        $source = array_pop($matches2);

                        $version = null;
                        if (preg_match(self::MODULE_VERSION_REGEX, $source, $matches) === 1) {
                            $version = array_pop($matches);
                        }

                        // Remove after ?ref= from source, so source only contains path and/or subpaths
                        if ($found = strpos($source, '?ref=')) {
                            $source = substr($source, 0, $found);
                        }

                        // If users mistakenly added //subpath AFTER the ?ref= then move it from version to source.
                        // This is invalid in terraform 0.12 code.
                        if ($found = strpos($version, '//')) {
                            $source = $source . substr($version, $found);
                            $version = substr($version, 0, $found);
                        }

                        $modules[] = [
                            'name' => $module,
                            'source' => $source,
                            'version' => $version,
                        ];
                    }
                }
            }
        }

        return $modules;
    }

    /**
     * @param string $input
     *
     * @return array|null
     */
    private function parsePrimaryModule($input)
    {
        if (!$input) {
            return [];
        }

        $lines = explode("\n", $input);
        foreach ($lines as $lineNumber => $original) {
            $line = trim($original);

            if (preg_match(self::PRIMARY_MODULE_REGEX, $line, $matches) === 1) {
                $source = array_pop($matches);

                $version = null;
                if (preg_match(self::MODULE_VERSION_REGEX, $source, $matches) === 1) {
                    $version = array_pop($matches);
                }

                // Remove after ?ref= from source, so source only contains path and/or subpaths
                if ($found = strpos($source, '?ref=')) {
                    $source = substr($source, 0, $found);
                }

                // If users mistakenly added //subpath AFTER the ?ref= then move it from version to source.
                // This is invalid in terraform 0.12 code.
                if ($found = strpos($version, '//')) {
                    $source = $source . substr($version, $found);
                    $version = substr($version, 0, $found);
                }

                return [
                    'name' => 'root',
                    'source' => $source,
                    'version' => $version,
                ];
            }
        }

        return null;
    }

    /**
     * @param ResourceChange $change
     * @param AttributeChange $attribute
     *
     * @return void
     */
    private function addChangedAttribute(ResourceChange $change, AttributeChange $attribute)
    {
        $old = $attribute->oldValue();
        $new = $attribute->newValue();

        if ($old && $old['value'] === $new['value']) {
            return;
        }

        $change->withAttribute($attribute->name(), $attribute);
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function stripANSICodes($input)
    {
        $output = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $input);
        return $output;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function sanitizeWindowsLineEndings($input)
    {
        $output = str_replace("\r\n", "\n", $input);
        return $output;
    }
}
