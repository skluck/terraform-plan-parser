<?php

namespace SK\TerraformParser;

use PHPUnit_Framework_TestCase as TestCase;

class TerraformOutputParserTest extends TestCase
{
    /**
     * @dataProvider providerTestCases
     */
    public function testOpaqueValueInputAndOutputAreEqual($input, $expected)
    {
        $parser = new TerraformOutputParser;

        $parsed = $parser->parse($input);
        $actual = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->assertSame($expected, $actual);
    }

    public function providerTestCases()
    {
        $fixturesDir = __DIR__ . '/.fixtures';

        $cases = [
            'standard-with-color' => '00-terraform-plan',
            'standard' => '01-terraform-plan',
            'with-errors' => '02-terraform-plan',
        ];

        return array_map(function ($case) use ($fixturesDir) {
            return [
                trim(file_get_contents("${fixturesDir}/${case}.stdout.txt")),
                trim(file_get_contents("${fixturesDir}/${case}.expected.json"))
            ];
        }, $cases);
    }

}
