<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

use SK\TerraformParser\Change\AttributeChange;
use SK\TerraformParser\Change\ResourceChange;

class TerraformOutputParser
{
    use ErrorHandlingTrait;

    const NO_CHANGES_STRING = "\nNo changes. Infrastructure is up-to-date.\n";
    const CONTENT_START_STRING = "\nTerraform will perform the following actions:\n";
    const CONTENT_END_STRING = "\nPlan:";

    const ATTRIBUTE_LINE_REGEX = '/^ {6}.+[:]{1}/';
    const ATTRIBUTE_FORCES_NEW_RESOURCE_SUFFIX = ' (forces new resource)';

    const ERR_START_NOT_FOUND = 'Could not find beginning of plan output.';
    const ERR_END_NOT_FOUND = 'Could not find end of plan output.';

    const ERR_INVALID_RESOURCE = 'Failed to parse resource name (line %s)';
    const ERR_INVALID_ATTRIBUTE = 'Failed to parse attribute (line %s)';

    /**
     * @var ResourceParser
     */
    private $resourceParser;

    /**
     * @var AttributeParser
     */
    private $attributeParser;

    /**
     * @param ResourceParser $resourceParser
     * @param AttributeParser $attributeParser
     */
    public function __construct(ResourceParser $resourceParser = null, AttributeParser $attributeParser = null)
    {
        $this->resourceParser = $resourceParser ?: new ResourceParser;
        $this->attributeParser = $attributeParser ?: new AttributeParser;
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    public function parseFile($filename)
    {
        $content = file_get_contents($filename);
        return $this->parse($content);
    }

    /**
     * @param string $input
     *
     * @return array
     */
    public function parse($input)
    {
        $this->resetErrors();

        $input = $this->stripANSICodes($input);
        $input = $this->sanitizeWindowsLineEndings($input);

        if (strpos($input, self::NO_CHANGES_STRING) !== false) {
            return [
                'errors' => $this->errors(),
                'changedResources' => [],
            ];
        }

        $changes = [];

        $output = $this->getChangeOutput($input);
        if ($output !== null) {
            $changes = $this->parseChanges($output);
        }

        return [
            'errors' => $this->errors(),
            'changedResources' => $changes,
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
