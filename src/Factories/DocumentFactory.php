<?php


namespace Firesphere\SolrSearch\Factories;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Firesphere\SolrSearch\Helpers\Statics;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
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
     * Numeral types in Solr
     * @var array
     */
    protected static $numerals = [
        'tint',
        'tfloat',
        'tdouble'
    ];
    /**
     * @var SearchIntrospection
     */
    protected $introspection;
    /**
     * @var null|ArrayList|DataList
     */
    protected $items;
    /**
     * @var string
     */
    protected $class;
    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var null|LoggerInterface
     */
    protected $logger;

    /**
     * DocumentFactory constructor, sets up introspection
     */
    public function __construct()
    {
        $this->introspection = Injector::inst()->get(SearchIntrospection::class);
    }

    /**
     * Note, it can only take one type of class at a time!
     * So make sure you properly loop and set $class
     * @param $fields
     * @param BaseIndex $index
     * @param Query $update
     * @return array
     * @throws Exception
     */
    public function buildItems($fields, $index, $update): array
    {
        $class = $this->getClass();
        $this->introspection->setIndex($index);
        $docs = [];

        $debugString = sprintf('Adding %s to %s%s', $class, $index->getIndexName(), PHP_EOL);
        if ($this->debug) {
            $debugString .= '[';
        }
        $boostFields = $index->getBoostedFields();
        foreach ($this->getItems() as $item) {
            if ($this->debug) {
                $debugString .= "$item->ID, ";
            }
            /** @var Document $doc */
            $doc = $update->createDocument();
            $this->addDefaultFields($doc, $item);

            $this->buildField($fields, $doc, $item, $boostFields);
            $item->destroy();

            $docs[] = $doc;
        }

        if ($this->debug) {
            $this->getLogger()->info(rtrim($debugString, ', ') . ']' . PHP_EOL);
        }

        unset($this->items);

        return $docs;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @param string $class
     * @return DocumentFactory
     */
    public function setClass(string $class): DocumentFactory
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return ArrayList|DataList|null
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param ArrayList|DataList|null $items
     * @return DocumentFactory
     */
    public function setItems($items): DocumentFactory
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @param Document $doc
     * @param DataObject|DataObjectExtension $item
     */
    protected function addDefaultFields(Document $doc, DataObject $item)
    {
        $doc->setKey(SolrCoreService::ID_FIELD, $item->ClassName . '-' . $item->ID);
        $doc->addField(SolrCoreService::CLASS_ID_FIELD, $item->ID);
        $doc->addField('ClassName', $item->ClassName);
        $doc->addField('ClassHierarchy', ClassInfo::ancestry($item));
        $doc->addField('ViewStatus', $item->getViewStatus());
    }

    /**
     * @param $fields
     * @param Document $doc
     * @param DataObject $item
     * @param array $boostFields
     * @throws Exception
     */
    protected function buildField($fields, Document $doc, DataObject $item, array $boostFields): void
    {
        foreach ($fields as $field) {
            $fieldData = $this->introspection->getFieldIntrospection($field);
            foreach ($fieldData as $dataField => $options) {
                // Only one field per class, so let's take the fieldData. This will override previous additions
                $this->addField($doc, $item, $fieldData[$dataField]);
                if (array_key_exists($field, $boostFields)) {
                    $doc->setFieldBoost($dataField, $boostFields[$field]);
                }
            }
        }
    }

    /**
     * @param Document $doc
     * @param $object
     * @param $field
     */
    protected function addField($doc, $object, $field): void
    {
        if (!$this->classIs($object, $field['origin'])) {
            return;
        }

        $valuesForField = $this->getValueForField($object, $field);

        $typeMap = Statics::getTypeMap();
        $type = $typeMap[$field['type']] ?? $typeMap['*'];

        while ($value = array_shift($valuesForField)) {
            if (!$value) {
                continue;
            }
            /* Solr requires dates in the form 1995-12-31T23:59:59Z */
            if ($type === 'tdate' || $value instanceof DBDate) {
                $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
            }

            /* Solr requires numbers to be valid if presented, not just empty */
            if (!is_numeric($value) && in_array($type, static::$numerals, true)) {
                continue;
            }

            $name = explode('\\', $field['name']);
            $name = end($name);

            $doc->addField($name, $value);
        }
        unset($value, $valuesForField, $type);
        gc_collect_cycles();
    }

    /**
     * Determine if the given object is one of the given type
     * @param string|array $class
     * @param array|string $base Class or list of base classes
     * @return bool
     */
    protected function classIs($class, $base): bool
    {
        $base = is_array($base) ? $base : [$base];

        foreach ($base as $nextBase) {
            if ($this->classEquals($class, $nextBase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a base class is an instance of the expected base group
     * @param $class
     * @param $base
     * @return bool
     */
    protected function classEquals($class, $base): bool
    {
        return $class === $base || ($class instanceof $base);
    }

    /**
     * Given an object and a field definition get the current value of that field on that object
     *
     * @param DataObject|array $objects - The object to get the value from
     * @param array $field - The field definition to use
     * @return array Technically, it's always an array
     */
    protected function getValueForField($objects, $field)
    {
        // Make sure we always have an array to iterate
        $objects = is_array($objects) ? $objects : [$objects];

        while ($step = array_shift($field['lookup_chain'])) {
            // If we're looking up this step on an array or SS_List, do the step on every item, merge result
            $next = [];

            foreach ($objects as $item) {
                $item = $this->getItemForStep($step, $item);

                if (is_array($item)) {
                    $next = array_merge($next, $item);
                } else {
                    $next[] = $item;
                }
                // Destroy the item(s) to clear out memory
                unset($item);
            }

            // When all objects have been processed, put them in to objects
            // This ensures the next step is an array of the correct objects to index
            $objects = $next;
            unset($next);
        }

        return $objects;
    }

    /**
     * Find the item for the current ste
     * This can be a DataList or ArrayList, or a string
     * @param $step
     * @param $item
     * @return array
     */
    protected function getItemForStep($step, $item)
    {
        if ($step['call'] === 'method') {
            $method = $step['method'];
            $item = $item->$method();
        } else {
            $property = $step['property'];
            $item = $item->$property;
        }

        if ($item instanceof SS_List) {
            $item = $item->toArray();
        }

        return is_array($item) ? $item : [$item];
    }

    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = Injector::inst()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    /**
     * @return SearchIntrospection
     */
    public function getIntrospection(): SearchIntrospection
    {
        return $this->introspection;
    }

    /**
     * @param bool $debug
     * @return DocumentFactory
     */
    public function setDebug(bool $debug): DocumentFactory
    {
        $this->debug = $debug;

        return $this;
    }
}
