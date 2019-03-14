<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser\Change;

use JsonSerializable;

class AttributeChange implements JsonSerializable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $old;

    /**
     * @var array
     */
    private $new;

    /**
     * @var bool
     */
    private $isForceNewResource;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;

        $this->old = null;
        $this->new = null;
        $this->isForceNewResource = false;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return array|null
     */
    public function oldValue()
    {
        return $this->old;
    }

    /**
     * @return array|null
     */
    public function newValue()
    {
        return $this->new;
    }

    /**
     * @return string
     */
    public function forceNewResource()
    {
        return $this->isForceNewResource;
    }

    /**
     * @param string $type
     * @param mixed $value
     *
     * @return self
     */
    public function withOldValue($type, $value)
    {
        $this->old = [
            'type' => $type,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param string $type
     * @param mixed $value
     *
     * @return self
     */
    public function withNewValue($type, $value)
    {
        $this->new = [
            'type' => $type,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param bool $isForceNewResource
     *
     * @return self
     */
    public function withForceNewResource($isForceNewResource)
    {
        $this->isForceNewResource = $isForceNewResource;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->name(),
            'force_new_resource' => $this->forceNewResource(),
            'old' => $this->oldValue(),
            'new' => $this->newValue(),
        ];
    }
}
