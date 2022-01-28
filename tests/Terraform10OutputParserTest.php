<?php

namespace SK\TerraformParser;

use PHPUnit\Framework\TestCase;

class Terraform10OutputParserTest extends TestCase
{
    /**
     * @dataProvider providerTestCases
     */
    public function testOpaqueValueInputAndOutputAreEqual($input, $expected)
    {
        $parser = new Terraform12OutputParser;

        $parsed = $parser->parse($input);
        $actual = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->assertSame($expected, $actual);
    }

    public function providerTestCases()
    {
        $fixturesDir = __DIR__ . '/.fixtures-1.0';

        $cases = [
            // 'create'               => '80-create',
            'update'               => '81-update',
            'changed-outside'      => '82-changed-outside',
            'nochanges'            => '83-nochanges',
            'resource-comment'     => '84-resource-comment',
            'debug-output'         => '85-debug-output',
            'output-only-changes'  => '86-output-only-changes',
        ];

        return array_map(function ($case) use ($fixturesDir) {
            return [
                file_get_contents("${fixturesDir}/${case}.stdout.txt"),
                trim(file_get_contents("${fixturesDir}/${case}.expected.json"))
            ];
        }, $cases);
    }
}
