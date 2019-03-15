<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

use SK\TerraformParser\Change\AttributeChange;

class AttributeParser
{
    use ErrorHandlingTrait;

    const OLD_NEW_SEPARATOR = ' => ';
    const FORCES_NEW_RESOURCE_SUFFIX = ' (forces new resource)';

    const TYPE_STRING = 'string';
    const TYPE_COMPUTED = 'computed';
    const TYPE_UNKNOWN = 'unknown';

    const ERR_UNTERMINATED_STRING = 'Found unterminated string';
    const ERR_UNTERMINATED_BRACKET = 'Found unterminated bracket';

    /**
     * @param string $line
     *
     * @return AttributeChange|null
     */
    public function parseLineForAttribute($line)
    {
        $this->resetErrors();

        if (($end = strpos($line, ':')) === false) {
            return null;
        }

        $name = trim(substr($line, 0, $end));
        $data = trim(substr($line, $end + 1));

        $parsed = $this->parseAttribute($data);
        $type = $parsed['type'];
        $value = $parsed['value'];
        $end = $parsed['end'];

        $change = new AttributeChange($name);
        
        if (substr($data, strlen(self::FORCES_NEW_RESOURCE_SUFFIX) * -1) === self::FORCES_NEW_RESOURCE_SUFFIX) {
            $change->withForceNewResource(true);
        }

        if ($end === null) {
            $change->withNewValue($type, $value);

        } elseif (substr($data, $end, strlen(self::OLD_NEW_SEPARATOR)) === self::OLD_NEW_SEPARATOR) {
            // there is a " => " so we have an old and new value
            $newData = substr($data, $end + strlen(self::OLD_NEW_SEPARATOR));
            $parsed = $this->parseAttribute($newData);

            $change
                ->withOldValue($type, $value)
                ->withNewValue($parsed['type'], $parsed['value']);

        } else {
            // there is no " => " so we only have a new value
            $change->withNewValue($type, $value);
        }

        return $change;
    }

    /**
     * @param string $data
     *
     * @return array
     */
    private function parseAttribute($data)
    {
        $first = substr($data, 0, 1);

        $type = self::TYPE_UNKNOWN;
        $value = null;
        $endPosition = null;

        if ($first === '"') {
            $end = $this->findStringEndDelimiterPosition($data);
            if ($end === null) {
                $this->addError(self::ERR_UNTERMINATED_STRING);
                $value = $data;
            } else {
                $type = self::TYPE_STRING;
                $content = substr($data, 0, $end + 1);
                $value = json_decode($content, true);
                $endPosition = $end + 1;
            }

        } elseif ($first === '<') {
            $endPos = strpos($data, '>', 1);
            if ($endPos === false) {
                $this->addError(self::ERR_UNTERMINATED_BRACKET);
                $value = $data;
            } else {
                $contents = substr($data, 1, $endPos - 1);
                $endPosition = strlen($contents) + 2;

                if ($contents === 'computed') {
                    $type = self::TYPE_COMPUTED;
                } else {
                    $value = $contents;
                }
            }

        } else {
            $value = $data;
        }

        return [
            'type' => $type,
            'value' => $value,
            'end' => $endPosition,
        ];
    }

    /**
     * @param string $content
     *
     * @return int|null
     */
    private function findStringEndDelimiterPosition($content)
    {
        $position = 1;
        $end = strlen($content);

        $escaped = false;

        while ($position < $end) {
            if ($escaped) {
                $escaped = false;
            } else {
                $char = $content[$position];
                if ($char === '"') {
                    return $position;
                } elseif ($char === '\\') {
                    $escaped = true;
                }
            }

            $position++;
        }

        return null;
    }
}
