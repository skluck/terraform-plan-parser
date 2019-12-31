<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

class TerraformOutputParser implements TerraformPlanParserInterface
{
    const RESOURCE_COMMENT_REGEX = '/ {1,}.+#.+ (will|must) be .+/';

    /**
     * @var TerraformPlanParserInterface
     */
    private $terraform11Parser;

    /**
     * @var TerraformPlanParserInterface
     */
    private $terraform12Parser;

    /**
     * @param TerraformPlanParserInterface|null $terraform11Parser
     * @param TerraformPlanParserInterface|null $terraform12Parser
     */
    public function __construct(?TerraformPlanParserInterface $terraform11Parser = null, ?TerraformPlanParserInterface $terraform12Parser = null)
    {
        $this->terraform11Parser = $terraform11Parser ?: new Terraform11OutputParser;
        $this->terraform12Parser = $terraform12Parser ?: new Terraform12OutputParser;
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    public function parseFile(string $filename): array
    {
        $content = file_get_contents($filename);
        return $this->parse($content);
    }

    /**
     * @param string $input
     *
     * @return array
     */
    public function parse(string $input): array
    {
        $parser = $this->terraform11Parser;

        $stripped = $this->stripANSICodes($input);
        if (preg_match(self::RESOURCE_COMMENT_REGEX, $stripped, $matches) === 1) {
            $parser = $this->terraform12Parser;
        }

        return $parser->parse($input);
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
}
