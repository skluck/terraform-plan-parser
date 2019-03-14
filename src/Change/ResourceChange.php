<?php
/**
 * @copyright (c) 2019 Steve Kluck
 *
 * For full license information, please view the LICENSE distributed with this source code.
 */

namespace SK\TerraformParser\Change;

use JsonSerializable;

class ResourceChange implements JsonSerializable
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $fullyQualifiedName;

    /**
     * @var string
     */
    private $modulePath;

    /**
     * @var bool
     */
    private $isTainted;

    /**
     * @var bool
     */
    private $isNew;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @param string $action
     * @param string $name
     */
    public function __construct($action, $name)
    {
        $this->action = $action;
        $this->name = $name;

        $this->fullyQualifiedName = '';
        $this->modulePath = '';
        $this->isTainted = false;
        $this->isNew = false;
        $this->attributes = [];
    }

    /**
     * @return string
     */
    public function action()
    {
        return $this->action;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function fullyQualifiedName()
    {
        return $this->fullyQualifiedName;
    }

    /**
     * @return string
     */
    public function modulePath()
    {
        return $this->modulePath;
    }

    /**
     * @return bool
     */
    public function isTainted()
    {
        return $this->isTainted;
    }

    /**
     * @return bool
     */
    public function isNew()
    {
        return $this->isNew;
    }

    /**
     * @return array
     */
    public function attributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $fullyQualifiedName
     *
     * @return self
     */
    public function withFullyQualifiedName($fullyQualifiedName)
    {
        $this->fullyQualifiedName = $fullyQualifiedName;
        return $this;
    }

    /**
     * @param string $modulePath
     *
     * @return self
     */
    public function withModulePath($modulePath)
    {
        $this->modulePath = $modulePath;
        return $this;
    }

    /**
     * @param bool $isNew
     *
     * @return self
     */
    public function withIsNew($isNew)
    {
        $this->isNew = $isNew;
        return $this;
    }

    /**
     * @param bool $isTainted
     *
     * @return self
     */
    public function withIsTainted($isTainted)
    {
        $this->isTainted = $isTainted;
        return $this;
    }

    /**
     * @param string $name
     * @param AttributeChange $change
     *
     * @return self
     */
    public function withAttribute($name, AttributeChange $change)
    {
        $this->attributes[$name] = $change;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'action' => $this->action(),
            'name' => $this->name(),
            'fully_qualified_name' => $this->fullyQualifiedName(),
            'module_path' => $this->modulePath(),
            'is_new' => $this->isNew(),
            'is_tainted' => $this->isTainted(),
            'attributes' => $this->attributes(),
        ];
    }
}
