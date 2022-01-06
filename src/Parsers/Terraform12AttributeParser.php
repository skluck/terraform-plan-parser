<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser\Parsers;

use SK\TerraformParser\Change\AttributeChange;
use SK\TerraformParser\ErrorHandlingTrait;

class Terraform12AttributeParser
{
    use ErrorHandlingTrait;

    const ATTRIBUTE_LINE_REGEX = '/^ {6,}((\~|\-|\+) )?(.+) +[=]{1} /';
    const BLOCK_LINE_REGEX = '/^ {6,}((\~|\-|\+) )?(.+) +\{$/';

    const USELESS_BLOCK_REGEX = '/^ {6,}((\~|\-|\+) )?(.+) +\{\}$/';

    const OLD_NEW_SEPARATOR = ' -> ';
    const FORCES_NEW_RESOURCE_SUFFIX = ' # forces replacement';
    const WHITESPACE_SUFFIX = ' # whitespace changes';

    const TYPE_STRING = 'string';
    const TYPE_COMPUTED = 'computed';
    const TYPE_LIST = 'list';
    const TYPE_MAP = 'map';
    const TYPE_BLOCK = 'block';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOL = 'bool';
    const TYPE_NULL = 'null';
    const TYPE_UNKNOWN = 'unknown';

    const ERR_UNTERMINATED_STRING = 'Found unterminated string';
    const ERR_UNTERMINATED_BRACKET = 'Found unterminated bracket';

    const MULTILINE_OPENERS = [
        '('      => '        )',
        '{'      => '        }',
        '['      => '        ]',
        '<<~EOT' => '        EOT',
        '<<-EOT' => '        EOT',
    ];

    /**
     * Empty blocks are unchanged, and should be ignored.
     *
     * Example:
     *
     * >
     * > + timeouts {}
     * >
     *
     * In Terraform 0.13+ unchanged blocks are not empty but rendered like this:
     *
     * > # (7 unchanged attributes hidden)
     * >
     * > # (1 unchanged block hidden)
     *
     * @param string $line
     *
     * @return bool
     */
    public function shouldIgnoreLine(string $line): bool
    {
        if (preg_match(self::USELESS_BLOCK_REGEX, $line) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Example:
     *
     * >  + ebs_block_device {
     * >
     * >
     * >    }
     * >
     * >  + tags = {
     * >
     * >
     * >    }
     * >
     * >  + cidr_blocks = [
     * >
     * >
     * >    ]
     * >
     * >  ~ cert_pem  = <<~EOT
     * >
     * >
     * >    EOT -> (known after apply)
     *
     * @param string $line
     *
     * @return string|null
     */
    public function isOpeningMultiLineTag($line): ?string
    {
        $line = $this->cleanLineFromSuffixes($line);

        if (preg_match(self::ATTRIBUTE_LINE_REGEX, $line) === 1) {
            foreach (array_keys(self::MULTILINE_OPENERS) as $opener) {
                if ($this->endsWith($line, $opener)) {
                    return self::MULTILINE_OPENERS[$opener];
                }
            }
        }

        if (preg_match(self::BLOCK_LINE_REGEX, $line) === 1) {
            return self::MULTILINE_OPENERS['{'];
        }

        return null;
    }

    /**
     * Examples:
     *
     * >  + from_port  = 0
     * >  + id         = (known after apply)
     *
     * @param string $line
     *
     * @return bool
     */
    public function isStandardAttribute($line)
    {
        $line = $this->cleanLineFromSuffixes($line);

        if (preg_match(self::ATTRIBUTE_LINE_REGEX, $line) !== 1) {
            return false;
        }

        foreach (self::MULTILINE_OPENERS as $opener) {
            if ($this->endsWith($line, $opener)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $line
     *
     * @return AttributeChange|null
     */
    public function parseLineForAttribute($line)
    {
        $this->resetErrors();

        if (($end = strpos($line, ' = ')) === false) {
            return null;
        }

        $name = trim(substr($line, 0, $end));
        $data = trim(substr($line, $end + 3));
        $forceNew = false;

        $suffixIndex = strlen(self::FORCES_NEW_RESOURCE_SUFFIX) * -1;
        if (substr($data, $suffixIndex) === self::FORCES_NEW_RESOURCE_SUFFIX) {
            $forceNew = true;
            $data = substr($data, 0, $suffixIndex);
        }

        if (($attrModifier = strpos($name, ' ')) !== false) {
            $modifier = substr($name, 0, $attrModifier);
            $name = substr($name, $attrModifier + 1);
        }

        $parsed = $this->parseAttribute($data);
        $type = $parsed['type'];
        $value = $parsed['value'];
        $end = $parsed['end'];

        $change = new AttributeChange($name);

        if ($forceNew) {
            $change->withForceNewResource(true);
        }

        if ($end === null) {
            $change->withNewValue($type, $value);

        } elseif (substr($data, $end, strlen(self::OLD_NEW_SEPARATOR)) === self::OLD_NEW_SEPARATOR) {
            // there is a " -> " so we have an old and new value
            $newData = substr($data, $end + strlen(self::OLD_NEW_SEPARATOR));
            $parsed = $this->parseAttribute($newData);

            $change
                ->withOldValue($type, $value)
                ->withNewValue($parsed['type'], $parsed['value']);

        } else {
            // there is no " -> " so we only have a new value
            $change->withNewValue($type, $value);
        }

        return $change;
    }

    /**
     * @param array<string> $lines
     *
     * @return AttributeChange|null
     */
    public function parseBlockForAttribute(array $lines)
    {
        $this->resetErrors();

        if (!$lines) {
            return null;
        }

        $firstLine = array_shift($lines);
        $lastLine = array_pop($lines);

        $forceNew = false;

        // It is possible for the "forces new" indicator to be on start or end of block
        // So this is the 1st check
        $suffixIndex = strlen(self::FORCES_NEW_RESOURCE_SUFFIX) * -1;
        if (substr($firstLine, $suffixIndex) === self::FORCES_NEW_RESOURCE_SUFFIX) {
            $forceNew = true;
            $firstLine = substr($firstLine, 0, $suffixIndex);
        }

        $name = $this->parseMultiline($firstLine);
        if (!$name) {
            return null;
        }

        $change = new AttributeChange($name);


        // It is possible for the "forces new" indicator to be on start or end of block
        // So this is the 2nd check
        if (substr($lastLine, $suffixIndex) === self::FORCES_NEW_RESOURCE_SUFFIX) {
            $forceNew = true;
        }

        $type = self::TYPE_UNKNOWN;

        if ($this->endsWith($firstLine, '= [')) {
            $type = self::TYPE_LIST;

        } elseif ($this->endsWith($firstLine, '= {')) {
            $type = self::TYPE_MAP;

        } elseif ($this->endsWith($firstLine, ' {')) {
            $type = self::TYPE_BLOCK;

        } elseif ($this->endsWith($firstLine, '(')) {
            // A bit wonky. This would be a computed string thru a function
            $type = self::TYPE_STRING;
        }

        $change->withForceNewResource($forceNew);

        // We currently record no values for multilines
        $change->withNewValue($type, null);

        return $change;
    }

    /**
     * @param string $line
     *
     * @return string|null
     */
    private function parseMultiline($line)
    {
        $name = null;

        if (preg_match(self::ATTRIBUTE_LINE_REGEX, $line, $matches) === 1) {
            $name = array_pop($matches);
            $name = trim($name);

        } elseif (preg_match(self::BLOCK_LINE_REGEX, $line, $matches) === 1) {
            $name = array_pop($matches);
            $name = trim($name);
        }

        return $name;
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
            // not certain if this is used in tf 0.12

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

        } elseif ($first === '[') {
            $type = self::TYPE_LIST;

        } elseif ($first === '{') {
            $type = self::TYPE_MAP;

        } elseif (($end = strpos($data, self::OLD_NEW_SEPARATOR)) !== false) {
            $endPosition = $end;
            $data = substr($data, 0, $end);

            if (in_array($data, ['true', 'false'], true)) {
                $type = self::TYPE_BOOL;
                $value = $data;

            } elseif ($data === '(known after apply)') {
                $type = self::TYPE_COMPUTED;

            } elseif ($data === 'null') {
                $type = self::TYPE_NULL;

            } elseif (is_numeric($data)) {
                $type = self::TYPE_NUMBER;
                $value = $data;
            } else {
                $value = $data;
            }

        } elseif (in_array($data, ['true', 'false'], true)) {
            $type = self::TYPE_BOOL;
            $value = $data;

        } elseif ($data === '(known after apply)') {
            $type = self::TYPE_COMPUTED;

        } elseif ($data === 'null') {
            $type = self::TYPE_NULL;

        } elseif (is_numeric($data)) {
            $type = self::TYPE_NUMBER;
            $value = $data;

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

    /**
     * @param string $line
     *
     * @return line
     */
    private function cleanLineFromSuffixes($line)
    {
        $suffixIndex = strlen(self::FORCES_NEW_RESOURCE_SUFFIX) * -1;
        if (substr($line, $suffixIndex) === self::FORCES_NEW_RESOURCE_SUFFIX) {
            $line = substr($line, 0, $suffixIndex);
        }

        $suffixIndex = strlen(self::WHITESPACE_SUFFIX) * -1;
        if (substr($line, $suffixIndex) === self::WHITESPACE_SUFFIX) {
            $line = substr($line, 0, $suffixIndex);
        }

        return $line;
    }

    /**
     * @param string $line
     * @param string $endsWith
     *
     * @return bool
     */
    private function endsWith($line, $endsWith)
    {
        $len = strlen($endsWith);

        return (substr($line, $len * -1) === $endsWith);
    }
}
