<?php


namespace Firesphere\SearchConfig\Factories;

use Exception;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Helpers\Statics;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\SS_List;
use Solarium\QueryType\Update\Query\Document\Document;
use Solarium\QueryType\Update\Query\Query;

class DocumentFactory
{

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    public function __construct()
    {
        $this->introspection = Injector::inst()->get(SearchIntrospection::class);
    }

    /**
     * @param $class
     * @param $fields
     * @param BaseIndex $index
     * @param Query $update
     * @param $group
     * @param int $count
     * @param bool $debug
     * @return array
     * @throws Exception
     * @todo this should be cleaner
     */
    public function buildItems($class, $fields, $index, $update, $group, &$count = 0, $debug = false)
    {
        $this->introspection->setIndex($index);
        $docs = [];
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        /** @var DataList|DataObject[] $items */
        // This limit is scientifically determined by keeping on trying until it didn't break anymore
        $items = $baseClass::get()
            ->sort('ID ASC')
            ->limit(2500, ($group * 2500));
        $count += $items->count();

        $debugString = sprintf("Adding %s to %s\n[", $class, $index->getIndexName());
        // @todo this is intense and could hopefully be simplified?

        $dbFields = DataObject::getSchema()->databaseFields($class, true);
        foreach ($items as $item) {
            $debugString .= "$item->ID, ";
            /** @var Document $doc */
            $doc = $update->createDocument();
            $this->addDefaultFields($doc, $item);
            $map = array_intersect_key($item->toMap(), array_flip($fields));
            $dbFields = array_intersect_key($dbFields, array_flip($fields));
            foreach ($dbFields as $column => $fieldType) {
                if (in_array($fieldType, ['PrimaryKey'])
                    || !isset($map[$column])
                ) {
                    continue;
                }
                if ($fieldType === 'ForeignKey') {
                    $field = Injector::inst()->create($fieldType, $column, $item);
                    $map[$column] = (int) $map[$column];
                } else {
                    $field = Injector::inst()->create($fieldType);
                }
                $formField = $field->scaffoldFormField();
                if ($formField instanceof UploadField) {
                    $map[$column] = (int) $map[$column];
                } else {
                    $formField->setValue($map[$column]);
                    $map[$column] = $formField->dataValue();
                }
                $doc->addField(ClassInfo::shortName($class) . '_' . $column, $map[$column]);
            }
            $item->destroy();
            $docs[] = $doc;
        }

        if ($debug) {
            Debug::message(rtrim($debugString, ', ') . "]\n", false);
            Debug::message(sprintf("Total added items: %s\n", $count), false);
        }

        return $docs;
    }

    /**
     * @param Document $doc
     * @param DataObject $item
     */
    protected function addDefaultFields(Document $doc, DataObject $item)
    {
        $doc->setKey('_documentid', $item->ClassName . '-' . $item->ID);
        $doc->addField('ID', $item->ID);
        $doc->addField('ClassName', $item->ClassName);
        $doc->addField('ClassHierarchy', ClassInfo::ancestry($item));
    }

    /**
     * @param Document $doc
     * @param $object
     * @param $field
     */
    protected function addField($doc, $object, $field)
    {
        $typeMap = Statics::getTypeMap();
        if (!$this->classIs(ClassInfo::shortName($object), $field['origin'])) {
            return;
        }

        $value = $this->getValueForField($object, $field);

        $type = isset($typeMap[$field['type']]) ? $typeMap[$field['type']] : $typeMap['*'];

        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $item) {
            /* Solr requires dates in the form 1995-12-31T23:59:59Z */
            if ($type === 'tdate' || $item instanceof DBDate) {
                if (!$item) {
                    continue;
                }
                $item = gmdate('Y-m-d\TH:i:s\Z', strtotime($item));
            }

            /* Solr requires numbers to be valid if presented, not just empty */
            if (($type === 'tint' || $type === 'tfloat' || $type === 'tdouble') && !is_numeric($item)) {
                continue;
            }

            $doc->addField($field['name'], $item);
        }
    }

    /**
     * Determine if the given object is one of the given type
     * @param string $class
     * @param array|string $base Class or list of base classes
     * @return bool
     * @todo copy-paste, needs refactoring
     *
     */
    protected function classIs($class, $base)
    {
        if (is_array($base)) {
            foreach ($base as $nextBase) {
                if ($this->classIs($class, $nextBase)) {
                    return true;
                }
            }

            return false;
        }

        // Check single origin
        return $class === $base || is_subclass_of($class, $base);
    }

    /**
     * Given an object and a field definition  get the current value of that field on that object
     *
     * @param DataObject|array|SS_List $object - The object to get the value from
     * @param array $field - The field definition to use
     * @return array|string|null - The value of the field, or null if we couldn't look it up for some reason
     * @todo refactor to something more readable
     */
    protected function getValueForField($object, $field)
    {
        if (!is_array($object)) {
            $object = [$object];
        }

        try {
            foreach ($field['lookup_chain'] as $step) {
                // Just fail if we've fallen off the end of the chain
                if (!count($object)) {
                    return null;
                }

                // If we're looking up this step on an array or SS_List, do the step on every item, merge result
                $next = [];

                foreach ($object as $item) {
                    if ($step['call'] === 'method') {
                        $method = $step['method'];
                        $item = $item->$method();
                    } else {
                        $property = $step['property'];
                        $item = $item->$property;
                    }

                    // @todo don't merge inside the foreach but merge after for memory/cpu efficiency
                    if ($item instanceof SS_List) {
                        $next = array_merge($next, $item->toArray());
                    } elseif (is_array($item)) {
                        $next = array_merge($next, $item);
                    } else {
                        $next[] = $item;
                    }
                }

                $object = $next;
            }
        } catch (Exception $e) {
            $object = null;
        }

        return $object;
    }
}
