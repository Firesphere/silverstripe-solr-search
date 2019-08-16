<?php

namespace Firesphere\SolrSearch\Helpers;

use Firesphere\SolrSearch\Traits\DataResolveTrait;
use LogicException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;

class DataResolver
{
    use DataResolveTrait;

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
     * @param DataObject|ArrayData|SS_List $component
     * @param array $columns
     *
     * @return void
     * @throws LogicException
     */
    protected function cannotIdentifyException($component, $columns = []): void
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

        throw new LogicException(sprintf('Class: %s is not supported.', ClassInfo::shortName($obj)));
    }
}
