<?php

namespace Firesphere\SolrSearch\Helpers;

use LogicException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;

class DataResolver
{
    /**
     * Supported object types
     * @var array map of objects to methods
     */
    private static $objTypes = [
        DataObject::class => 'DataObject',
        ArrayData::class  => 'ArrayData',
        SS_List::class    => 'List',
        DBField::class    => 'Field'
    ];
    /**
     * @var DataObject|ArrayList|SS_List|DBField
     */
    protected $component;
    /**
     * @var string
     */
    protected $shortName;
    /**
     * @var array
     */
    protected $columns = [];
    /**
     * @var mixed|string|null
     */
    protected $columnName = '';

    /**
     * DataResolver constructor.
     * @param DataObject|ArrayList|SS_List|DBField $component
     * @param array|string $columns
     */
    public function __construct($component, $columns = [])
    {
        if (!is_array($columns)) {
            $columns = array_filter(explode('.', $columns));
        }
        $this->columns = $columns;
        $this->component = $component;
        $this->columnName = $this->columns ? array_shift($this->columns) : null;
        $this->shortName = ClassInfo::shortName($component);
    }

    /**
     * Resolves an ArrayData value
     * @return mixed
     * @throws LogicException
     */
    public function resolveArrayData()
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
     * Resolves a DataList values
     * @return array|mixed
     * @throws LogicException
     */
    public function resolveList()
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
     * @return mixed
     * @throws LogicException
     */
    protected function resolveField()
    {
        if ($this->columnName) {
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
     * @param DataObject|ArrayData|SS_List $component
     * @param array $columns
     *
     * @return void
     * @throws LogicException
     */
    protected function cannotIdentifyException($component, $columns = [])
    {
        throw new LogicException(
            sprintf(
                'Cannot identify, "%s" from class "%s"',
                implode('.', $columns),
                ClassInfo::shortName($component)
            )
        );
    }

    /**
     * Resolves a DataObject value
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
            $relation = $this->columnName;

            return self::identify($this->component->$relation(), $this->columns);
        }
        // Inspect component has attribute
        if ($this->component->hasField($this->columnName)) {
            $data = $this->component->{$this->columnName};
            $dbObject = $this->component->dbObject($this->columnName);
            if ($dbObject) {
                // @todo do we need to set the value?
                $dbObject->setValue($data);

                return self::identify($dbObject, $this->columns);
            }

            return $data;
        }
        $this->cannotIdentifyException($this->component, [$this->columnName]);
    }

    /**
     * @param DataObject|ArrayData|SS_List|DBField $obj
     * @param array|string $columns
     *
     * @return mixed
     * @throws LogicException
     */
    public static function identify($obj, $columns = [])
    {
        /** @var @see {self::$objTypes} $type */
        foreach (self::$objTypes as $type => $method) {
            if ($obj instanceof $type) {
                $method = 'resolve' . $method;

                return (new self($obj, $columns))->{$method}();
            }
        }
        // We hit a direct method that returns a non-object
        if (!is_object($obj) && !is_array($obj)) {
            return $obj;
        }

        throw new LogicException(sprintf('Class: %s, is not supported.', ClassInfo::shortName($obj)));
    }
}
