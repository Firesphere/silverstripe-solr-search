<?php

namespace Firesphere\SolrSearch\Traits;

use LogicException;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;

/**
 * Trait ResolveTrait All resolver methods for the DataResolver
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait DataResolveTrait
{
    /**
     * @var DataObject|ArrayList|SS_List|DBField
     */
    protected $component;
    /**
     * @var array
     */
    protected $columns = [];
    /**
     * @var mixed|string|null
     */
    protected $columnName = '';
    /**
     * @var string
     */
    protected $shortName;

    /**
     * Resolves an ArrayData value
     *
     * @return mixed
     * @throws LogicException
     */
    protected function resolveArrayData()
    {
        if (empty($this->columnName)) {
            return $this->component->toMap();
        }
        // Inspect component has attribute
        if (empty($this->columns) && $this->component->hasField($this->columnName)) {
            return $this->component->{$this->columnName};
        }
        $this->cannotIdentifyException($this->component, array_merge([$this->columnName], $this->columns));
    }

    /**
     * @param DataObject|ArrayData|SS_List $component
     * @param array $columns
     *
     * @return void
     * @throws LogicException
     */
    abstract protected function cannotIdentifyException($component, $columns = []): void;

    /**
     * Resolves a DataList values
     *
     * @return array|mixed
     * @throws LogicException
     */
    protected function resolveList()
    {
        if (empty($this->columnName)) {
            return $this->component->toNestedArray();
        }
        // Inspect $component for element $relation
        if ($this->component->hasMethod($this->columnName)) {
            $relation = $this->columnName;

            return self::identify($this->component->$relation(), $this->columns);
        }
        $data = [];
        array_unshift($this->columns, $this->columnName);
        foreach ($this->component as $component) {
            $data[] = self::identify($component, $this->columns);
        }

        return $data;
    }

    /**
     * Resolves a Single field in the database.
     *
     * @return mixed
     * @throws LogicException
     */
    protected function resolveField()
    {
        if ($this->columnName) {
            // @todo could be simplified if possible?
            if ($this->component->hasMethod($this->columnName)) {
                $method = $this->columnName;
            } elseif ($this->component->hasMethod("get{$this->columnName}")) {
                $method = "get{$this->columnName}";
            } else {
                throw new LogicException(
                    sprintf('Method, "%s" not found on "%s"', $this->columnName, $this->shortName)
                );
            }

            $value = $this->component->$method();
        } else {
            $value = $this->component->getValue();
        }

        if (!empty($this->columns)) {
            $this->cannotIdentifyException($this->component, $this->columns);
        }

        return $value;
    }

    /**
     * Resolves a DataObject value
     *
     * @return mixed
     * @throws LogicException
     */
    protected function resolveDataObject()
    {
        if (empty($this->columnName)) {
            return $this->component->toMap();
        }
        // Inspect component for element $relation
        if ($this->component->hasMethod($this->columnName)) {
            return $this->getMethodValue();
        }
        // Inspect component has attribute
        if ($this->component->hasField($this->columnName)) {
            return $this->getFieldValue();
        }
        $this->cannotIdentifyException($this->component, [$this->columnName]);
    }

    /**
     * @return mixed
     */
    protected function getMethodValue()
    {
        $relation = $this->columnName;
        // We hit a direct method that returns a non-object
        if (!is_object($this->component->$relation())) {
            return $this->component->$relation();
        }

        return self::identify($this->component->$relation(), $this->columns);
    }

    /**
     * @return mixed
     */
    protected function getFieldValue()
    {
        $data = $this->component->{$this->columnName};
        $dbObject = $this->component->dbObject($this->columnName);
        if ($dbObject) {
            // @todo do we need to set the value?
            $dbObject->setValue($data);

            return self::identify($dbObject, $this->columns);
        }

        return $data;
    }
}
