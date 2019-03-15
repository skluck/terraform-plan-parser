<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser;

trait ErrorHandlingTrait
{
    /**
     * @var array
     */
    private $errors;

    /**
     * @return array
     */
    public function errors()
    {
        if ($this->errors === null) {
            $this->resetErrors();
        }

        return $this->errors;
    }

    /**
     * @return void
     */
    private function resetErrors()
    {
        $this->errors = [];
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * @param string $msg
     *
     * @return void
     */
    private function addError($msg)
    {
        if ($this->errors === null) {
            $this->resetErrors();
        }

        $this->errors[] = $msg;
    }
}
