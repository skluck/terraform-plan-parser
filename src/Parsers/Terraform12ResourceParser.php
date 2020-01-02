<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser\Parsers;

use SK\TerraformParser\Change\ResourceChange;
use SK\TerraformParser\ErrorHandlingTrait;

class Terraform12ResourceParser
{
    use ErrorHandlingTrait;

    const ACTION_LINE_REGEX =
        '/^' .
            '(?:data|resource)' . # resource
        '\ ' .
            '"([^.]+)"' .         # type
        '\ ' .
            '"([^ ]+)"' .         # name
        '\ {1,}\{' .
        '$/';

    const COMMENT_LINE_REGEX =
        '/^' .
            '#{1}' .
        '\ ' .
            '([^ ]+)' .             # full name
            '( is tainted\, so)?' . # tainted (optional)
        '\ ' .
            '(?:will|must) be' .
        '\ ' .
            '([^\#]+)' .             # type
        '$/';

    const ACTIONS = [
        '+' => 'create',
        '-' => 'destroy',
        '-/+' => 'replace',
        '~' => 'update',
        '<=' => 'read'
    ];

    const COMMENT_ACTIONS = [
        'created' => 'create',
        'destroyed' => 'destroy',
        'replaced' => 'replace',
        'updated' => 'update',
        'updated in-place' => 'update',
        'read during apply' => 'read'
    ];

    const MODULE_PATH_REGEX =
        '/^' .
            '(?:((?:.*\.)?module\.[^.]*)\.)?' .
        '/';

    const MODULE_USELESS_COMMENT = '# (config refers to values not yet known)';

    const ERR_FAILED_PARSE = 'Failed to parse resource header';
    const ERR_FAILED_PARSE_COMMENT = 'Failed to parse resource comment';

    /**
     * @param string $line
     *
     * @return bool
     */
    public function shouldIgnoreLine(string $line): bool
    {
        // Not necessary information
        if (ltrim($line) === self::MODULE_USELESS_COMMENT) {
            return true;
        }

        $symbol = trim(substr($line, 0, 3));
        $offset = 4;

        // Does not start with modified symbol -> do not skip
        if (!$read = self::ACTIONS[$symbol] ?? null) {
            return false;
        }

        $line = substr($line, $offset);

        // Everything in the resource header is available in the comment, already parsed.
        if (preg_match(self::ACTION_LINE_REGEX, $line) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param string $line
     *
     * @return ResourceChange|null
     */
    public function parseCommentForResource(string $line): ?ResourceChange
    {
        $this->resetErrors();

        $line = trim($line);

        if (substr($line, 0, 1) !== '#') {
            return null;
        }

        if (preg_match(self::COMMENT_LINE_REGEX, $line, $matches) !== 1) {
            $this->addError(self::ERR_FAILED_PARSE_COMMENT);
            return null;
        }

        array_shift($matches);

        $name = $matches[0];
        $isTainted = $matches[1] ? true : false;
        $symbol = $matches[2];

        return $this->parseResource($symbol, $name, $isTainted);
    }

    /**
     * @param string $symbol
     * @param string $fullyQualifiedName
     * @param bool $isTainted
     *
     * @return ResourceChange|null
     */
    private function parseResource(string $symbol, string $fullyQualifiedName, bool $isTainted)
    {
        $action = array_key_exists($symbol, self::COMMENT_ACTIONS) ? self::COMMENT_ACTIONS[$symbol] : null;
        if (!$action) {
            return null;
        }

        $parts = explode('.', $fullyQualifiedName);
        $name = array_pop($parts);
        $type = array_pop($parts);

        $change = (new ResourceChange($action, $name))
            ->withType($type)
            ->withFullyQualifiedName($fullyQualifiedName)
            // ->withIsNew($isNew)
            ->withIsTainted($isTainted);

        if (preg_match(self::MODULE_PATH_REGEX, $fullyQualifiedName, $matches) === 1) {
            $module = array_pop($matches);
            $path = $this->parseModulePath($module);
            $change->withModulePath($path);
        }

        return $change;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function parseModulePath($input)
    {
        $modules = preg_split('/\.?module./', $input);
        return $this->collapse($modules);
    }

    /**
     * @param array $parts
     * @param string $delimiter
     *
     * @return string
     */
    private function collapse(array $parts, $delimiter = '.')
    {
        $fq = [];

        foreach ($parts as $x) {
            if (strlen($x) > 0) {
                $fq[] = $x;
            }
        }

        return implode($delimiter, $fq);
    }
}
