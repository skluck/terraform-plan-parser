<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

interface TerraformPlanParserInterface
{
    /**
     * @param string $filename
     *
     * @return array
     */
    public function parseFile(string $filename): array;

    /**
     * @param string $input
     *
     * @return array
     */
    public function parse(string $input): array;
}
