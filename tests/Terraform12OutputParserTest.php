<?php

namespace SK\TerraformParser;

use PHPUnit_Framework_TestCase as TestCase;

class Terraform12OutputParserTest extends TestCase
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

    /**
     * @dataProvider providerTestCasesForModules
     */
    public function testModuleInputAndOutputAreEqual($input, $expected)
    {
        $parser = new Terraform12OutputParser;

        $parsed = $parser->parse($input);
        $actual = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->assertSame($expected, $actual);
    }

    public function providerTestCases()
    {
        $fixturesDir = __DIR__ . '/.fixtures-0.12';

        $cases = [
            'create'       => '60-create',
            'update'       => '61-update',
            'destroy'      => '62-destroy',
            'with-modules' => '63-modules',
            'tainted'      => '64-tainted',
            'no-changes'   => '65-no-changes',
            'whitespace'   => '66-whitespace-changes',
            'deposed'      => '67-deposed',
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
        $fixturesDir = __DIR__ . '/.fixtures-0.12';

        $cases = [
            'standard'            => '70-modules',
            'with-versions'       => '71-modules-with-versions',
            'with-primary-remote' => '72-primary-module-terragrunt',
            'with-primary-local'  => '73-primary-module-terragrunt-local',
            'as-submodule'        => '74-primary-module-as-submodule',
        ];

        return array_map(function ($case) use ($fixturesDir) {
            return [
                file_get_contents("${fixturesDir}/${case}.stdout.txt"),
                trim(file_get_contents("${fixturesDir}/${case}.expected.json"))
            ];
        }, $cases);
    }
}
