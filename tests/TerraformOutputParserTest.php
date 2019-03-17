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

    /**
     * @dataProvider providerTestCasesForModules
     */
    public function testModuleInputAndOutputAreEqual($input, $expected)
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
            'no-attributes' => '03-terraform-plan',
            'no-magic-start' => '04-no-magic-start',
            'no-magic-end' => '05-no-magic-end',
            'attribute-value-unxpected-delimiter' => '06-attribute-value-unexpected-delimiter',
            'invalid-action-line' => '07-invalid-action-line',
            'no-attribute-name' => '08-no-attribute-name',
            'windows' => '09-terraform-plan-windows-line-end',
            'issue-4' => '10-issue-4',
            'tainted' => '11-tainted-resource',
            'modules' => '12-modules',
            'no-changes' => '13-no-changes',
        ];

        return array_map(function ($case) use ($fixturesDir) {
            return [
                file_get_contents("${fixturesDir}/${case}.stdout.txt"),
                trim(file_get_contents("${fixturesDir}/${case}.expected.json"))
            ];
        }, $cases);
    }

    public function providerTestCasesForModules()
    {
        $fixturesDir = __DIR__ . '/.fixtures';

        $cases = [
            'standard' => '50-modules',
            'with-versions' => '51-modules-with-versions',
            'with-primary' => '52-primary-module',
        ];

        return array_map(function ($case) use ($fixturesDir) {
            return [
                file_get_contents("${fixturesDir}/${case}.stdout.txt"),
                trim(file_get_contents("${fixturesDir}/${case}.expected.json"))
            ];
        }, $cases);
    }
}
