<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

use SK\TerraformParser\Change\ResourceChange;

class ResourceParser
{
    use ErrorHandlingTrait;

    const ACTION_LINE_REGEX =
        '/^' .
            '(?:((?:.*\.)?module\.[^.]*)\.)?' . # module (optional)
            '(?:(data)\.)?' .                   # data (optional)
            '([^.]+)' .                         # type
        '\.' .
            '([^ ]+)' .                         # name
            '( \(tainted\))?' .                 # tainted (optional)
            '( \(new resource required\))?' .   # new (optional)
        '$/';

    const ACTIONS = [
        '+' => 'create',
        '-' => 'destroy',
        '-/+' => 'replace',
        '~' => 'update',
        '<=' => 'read'
    ];

    const ERR_FAILED_PARSE = 'Failed to parse resource header';

    /**
     * Parse a line such as this:
     * -/+ aws_ecs_task_definition.sample_app (new resource required)
     *
     * into ResourceChange object such that:
     *  - $change->action() : "replace"
     *  - $change->name() : "sample_app"
     *  - $change->isNew() : true
     *  - $change->isTainted() : false
     *  - $change->fullyQualifiedName() : "aws_ecs_task_definition.sample_app"
     *  - $change->modulePath() : ""
     *
     * @param string $line
     *
     * @return ResourceChange|null
     */
    public function parseLineForResource($line)
    {
        $this->resetErrors();

        $symbol = trim(substr($line, 0, 3));

        $divider = strpos($symbol, ' ');
        if ($divider === false) {
            $offset = 4;
        } else {
            $symbol = substr($line, 0, $divider);
        }

        $action = array_key_exists($symbol, self::ACTIONS) ? self::ACTIONS[$symbol] : null;
        if (!$action) {
            return null;
        }

        $name = substr($line, strlen($symbol) + 1);

        return $this->parseResourceHeader($action, $name);
    }

    /**
     * @param string $action
     * @param string $name
     *
     * @return ResourceChange|null
     */
    private function parseResourceHeader($action, $name)
    {
        if (preg_match(self::ACTION_LINE_REGEX, $name, $matches) !== 1) {
            $this->addError(self::ERR_FAILED_PARSE);
            return null;
        }

        array_shift($matches);

        $module = $matches[0];
        $dataSource = $matches[1];
        $type = $matches[2];
        $name = $matches[3];

        $isTainted = isset($matches[4]) && $matches[4] ? true : false;
        $isNew = isset($matches[5]) && $matches[5] ? true : false;

        $fq = $this->collapse([$module, $dataSource, $type, $name]);

        $change = (new ResourceChange($action, $name))
            ->withType($type)
            ->withFullyQualifiedName($fq)
            ->withIsNew($isNew)
            ->withIsTainted($isTainted);

        if ($module) {
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
