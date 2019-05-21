<?php


namespace Firesphere\SearchConfig\Factories;

use Exception;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Helpers\Statics;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
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

    /**
     * @param $classes
     * @param $fields
     * @param $index
     * @param Query $update
     * @return array
     * @throws Exception
     */
    public function buildItems($class, $fields, $index, $update, $debug = false)
    {
        $this->introspection = Injector::inst()->get(SearchIntrospection::class);
        $this->introspection->setIndex($index);
        $docs = [];
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $items = $baseClass::get()->limit(10)
            ->limit(5000);

        // @todo this is intense and could hopefully be simplified?
        foreach ($items as $item) {
            $debug[] = "Adding $item->ClassName with ID $ID\n";
            $doc = $update->createDocument();
            $doc->setKey($item->ClassName . '-' . $item->ID);
            $doc->addField('_documentid', $item->ClassName . '-' . $item->ID);
            $doc->addField('ID', $item->ID);
            $doc->addField('ClassName', $item->ClassName);
            $doc->addField('ClassHierarchy', ClassInfo::ancestry($item));

            foreach ($fields as $field) {
                $fieldData = $this->introspection->getFieldIntrospection($field);
                $fieldName = ClassInfo::shortName($class) . '_' . str_replace('.', '_', $field);
                $this->addField($doc, $item, $fieldData[$fieldName]);
            }
            $item->destroy();

            $docs[] = $doc;
        }

        if ($debug) {
            Debug::dump($debug);
        }

        return $docs;
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
