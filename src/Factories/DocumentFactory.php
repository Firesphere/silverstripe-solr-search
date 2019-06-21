<?php


namespace Firesphere\SolrSearch\Factories;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Firesphere\SolrSearch\Helpers\Statics;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\SS_List;
use Solarium\QueryType\Update\Query\Document\Document;
use Solarium\QueryType\Update\Query\Query;

class DocumentFactory
{
    use Configurable;

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    /**
     * DocumentFactory constructor, sets up introspection
     */
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
     * @param null|ArrayList $items
     * @return array
     * @throws Exception
     * @todo this could be cleaner
     */
    public function buildItems($class, $fields, $index, $update, $group, &$count = 0, $debug = false, $items = null)
    {
        $this->introspection->setIndex($index);
        $docs = [];
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        /** @var DataList|DataObject[] $items */
        $batchLength = self::config()->get('batchLength');
        if (!$items) {
            // This limit is scientifically determined by keeping on trying until it didn't break anymore
            $items = $baseClass::get()
                ->sort('ID ASC')
                ->limit($batchLength, ($group * $batchLength));
            $count += $items->count();
        }

        $debugString = sprintf("Adding %s to %s\n[", $class, $index->getIndexName());
        $boostFields = $index->getBoostedFields();
        // @todo this is intense and could hopefully be simplified?
        foreach ($items as $item) {
            $debugString .= "$item->ID, ";
            /** @var Document $doc */
            $doc = $update->createDocument();
            $this->addDefaultFields($doc, $item);

            foreach ($fields as $field) {
                $fieldData = $this->introspection->getFieldIntrospection($field);
                foreach ($fieldData as $dataField => $options) {
                    // Only one field per class, so let's take the f
                    $this->addField($doc, $item, $fieldData[$dataField]);
                    if (array_key_exists($field, $boostFields)) {
                        $doc->setFieldBoost($dataField, $boostFields[$field]);
                    }
                }
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
     * @param DataObject|DataObjectExtension $item
     */
    protected function addDefaultFields(Document $doc, DataObject $item)
    {
        $doc->setKey('_documentid', $item->ClassName . '-' . $item->ID);
        $doc->addField('ID', $item->ID);
        $doc->addField('ClassName', $item->ClassName);
        $doc->addField('ClassHierarchy', ClassInfo::ancestry($item));
        $doc->addField('ViewStatus', $item->getViewStatus());
    }

    /**
     * @param Document $doc
     * @param $object
     * @param $field
     */
    protected function addField($doc, $object, $field)
    {
        $typeMap = Statics::getTypeMap();
        if (!$this->classIs($object, $field['origin'])) {
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

            $name = explode('\\', $field['name']);
            $name = end($name);

            $doc->addField($name, $item);
        }
    }

    /**
     * Determine if the given object is one of the given type
     * @param string|array $class
     * @param array|string $base Class or list of base classes
     * @return bool
     * @todo copy-paste, needs refactoring
     * @todo This can be handled by PHP built-in Class determination, e.g. InstanceOf
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
        return $class === $base || ($class instanceof $base);
    }

    /**
     * Given an object and a field definition  get the current value of that field on that object
     *
     * @param DataObject|array|SS_List $object - The object to get the value from
     * @param array $field - The field definition to use
     * @return array|string|null - The value of the field, or null if we couldn't look it up for some reason
     * @todo reduced the array_merge need to something more effective
     */
    protected function getValueForField($object, $field)
    {
        if (!is_array($object)) {
            $object = [$object];
        }

        foreach ($field['lookup_chain'] as $step) {
            // Just fail if we've fallen off the end of the chain
            if (!count($object)) {
                return null;
            }

            // If we're looking up this step on an array or SS_List, do the step on every item, merge result
            $next = [];

            foreach ($object as $item) {
                if ($step['call'] === 'method') { // php's built_in method_exists() is faster
                    $method = $step['method'];
                    $item = $item->$method();
                } else {
                    $property = $step['property'];
                    $item = $item->$property;
                }

                // @todo don't merge inside the foreach but merge after for memory/cpu efficiency
                if ($item instanceof SS_List) {
                    $item = $item->toArray();
                }
                if (is_array($item)) {
                    $next = array_merge($next, $item);
                } else {
                    $next[] = $item;
                }
            }

            $object = $next;
        }

        return $object;
    }

    /**
     * @return SearchIntrospection
     */
    public function getIntrospection(): SearchIntrospection
    {
        return $this->introspection;
    }
}
