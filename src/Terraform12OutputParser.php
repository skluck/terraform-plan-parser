<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

use SK\TerraformParser\Change\AttributeChange;
use SK\TerraformParser\Change\ResourceChange;
use SK\TerraformParser\Parsers\Terraform12AttributeParser;
use SK\TerraformParser\Parsers\Terraform12ResourceParser;

class Terraform12OutputParser
{
    use ErrorHandlingTrait;

    const NO_CHANGES_STRING = "\nNo changes. Infrastructure is up-to-date.\n";
    const CONTENT_START_STRING = "\nTerraform will perform the following actions:\n";
    const CONTENT_END_STRING = "\nPlan:";

    const MODULES_START_STRING = "Initializing modules...\n";
    const MODULES_END_STRING = "\nInitializing the backend...";
    const MODULE_LINE_REGEX = '/^\- ([^ ]+) in ([^ ]+)/';
    const MODULE_DOWNLOAD_REGEX = '/^Downloading ([^ ]+) for ([^ ]+)\.\.\.$/';
    const MODULE_VERSION_REGEX = '/\?ref\=(.+)/';

    const PRIMARY_MODULE_START_STRING = "Downloading Terraform configurations from ";
    const PRIMARY_MODULE_REGEX = '/Downloading Terraform configurations from ([^\s]+) into/';

    const END_RESOURCE = '    }';

    const ERR_START_NOT_FOUND = 'Could not find beginning of plan output.';
    const ERR_END_NOT_FOUND = 'Could not find end of plan output.';

    const ERR_INVALID_RESOURCE = 'Failed to parse resource name (line %s)';
    const ERR_INVALID_ATTRIBUTE = 'Failed to parse attribute (line %s)';

    /**
     * @var Terraform12ResourceParser
     */
    private $resourceParser;

    /**
     * @var Terraform12AttributeParser
     */
    private $attributeParser;

    /**
     * @param Terraform12ResourceParser $resourceParser
     * @param Terraform12AttributeParser $attributeParser
     */
    public function __construct(
        ?Terraform12ResourceParser $resourceParser = null,
        ?Terraform12AttributeParser $attributeParser = null
    ) {
        $this->resourceParser = $resourceParser ?: new Terraform12ResourceParser;
        $this->attributeParser = $attributeParser ?: new Terraform12AttributeParser;
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

        $isInMultiline = null;
        $multiline = [];

        $lines = explode("\n", $input);
        foreach ($lines as $lineNumber => $original) {
            $line = rtrim($original);

            // empty can exist between resources, as well as within resources, so just ignore them all.
            if (strlen($line) === 0) {
                continue;
            }

            // Closing brace. Save the recorded resource and reset.
            if ($original === self::END_RESOURCE) {
                if ($lastChange) {
                    $resources[] = $lastChange;
                }

                // Reset ongoing counters
                $lastChange = null;
                $isInMultiline = null;
                $multiline = [];
                continue;
            }

            // End of multiline attribute. Save the attribute and reset.
            if ($isInMultiline) {
                $multiline[] = $line;

                // stripos is used here because it may have suffixes such as:
                // ","
                // "# forces replacement"
                // -> "some other value"
                if (stripos($line, $isInMultiline) === 0) {
                    // closing tag. End and parse.
                    if ($lastChange && ($attribute = $this->attributeParser->parseBlockForAttribute($multiline))) {
                        $this->addChangedAttribute($lastChange, $attribute);

                        if ($this->attributeParser->hasErrors()) {
                            foreach ($this->attributeParser->errors() as $error) {
                                $this->addError($error);
                            }
                        }

                    } else {
                        $this->addError(sprintf(self::ERR_INVALID_ATTRIBUTE, $lineNumber));
                    }

                    $isInMultiline = null;
                    $multiline = [];
                }

                continue;
            }

            if ($this->resourceParser->shouldIgnoreLine($line)) {
                continue;
            }

            $resourceChange = $this->resourceParser->parseCommentForResource($line);

            if ($resourceChange) {
                $lastChange = $resourceChange;

            } elseif ($this->resourceParser->hasErrors()) {
                $this->addError(sprintf(self::ERR_INVALID_RESOURCE, $lineNumber));

            } elseif ($closingTag = $this->attributeParser->isOpeningMultiLineTag($line)) {
                // Order matters. We must process blocks before single line attributes
                $isInMultiline = $closingTag;
                $multiline = [$line];

            } elseif ($this->attributeParser->shouldIgnoreLine($line)) {
                // Ignore useless attributes (unchanged)

            } elseif ($this->attributeParser->isStandardAttribute($line)) {
                // easy. single-line property
                if ($lastChange && ($attribute = $this->attributeParser->parseLineForAttribute($line))) {
                    $this->addChangedAttribute($lastChange, $attribute);

                    if ($this->attributeParser->hasErrors()) {
                        foreach ($this->attributeParser->errors() as $error) {
                            $this->addError($error);
                        }
                    }
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
     * For Terraform 0.12 we are losing the "submodule path" for remote modules such as:
     *
     *                                                    | <--    this section    --> |
     * https://git.example.com/custom-modules/mymodule.git//modules/my-submodule-example?ref=1.1.5
     *
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
        $downloaded = [];

        $lines = explode("\n", $input);
        foreach ($lines as $lineNumber => $original) {
            $line = trim($original);

            // Here we record the downloading of a module, for later reference.
            if (preg_match(self::MODULE_DOWNLOAD_REGEX, $line, $matches) === 1) {
                array_shift($matches);
                $source = array_shift($matches);
                $module = array_shift($matches);
                $downloaded[$module] = $source;
                continue;
            }

            if (preg_match(self::MODULE_LINE_REGEX, $line, $matches) === 1) {
                array_shift($matches);
                $module = array_shift($matches);
                $source = array_shift($matches);

                // If the module was previously downloaded, switch the source to the download link
                if (isset($downloaded[$module])) {
                    $source = $downloaded[$module];
                }

                $version = null;
                if (preg_match(self::MODULE_VERSION_REGEX, $source, $matches) === 1) {
                    $version = array_pop($matches);
                }

                $modules[] = [
                    'name' => $module,
                    'source' => $source,
                    'version' => $version,
                ];
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
