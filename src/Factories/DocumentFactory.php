<?php


namespace Firesphere\SolrSearch\Factories;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\DataResolver;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use Firesphere\SolrSearch\Helpers\Statics;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\DocumentFactoryTrait;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use LogicException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use Solarium\QueryType\Update\Query\Document;
use Solarium\QueryType\Update\Query\Query;

class DocumentFactory
{
    use Configurable;
    use Extensible;
    use DocumentFactoryTrait;
    use LoggerTrait;

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
        $this->introspection = Injector::inst()->get(FieldResolver::class);
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
        $this->getIntrospection()->setIndex($index);
        $boostFields = $index->getBoostedFields();
        $docs = [];
        $debugString = sprintf('Adding %s to %s%s', $class, $index->getIndexName(), PHP_EOL);
        $debugString .= '[';
        foreach ($this->getItems() as $item) {
            if ($item->ShowInSearch === 0) {
                continue;
            }
            $debugString .= "$item->ID, ";
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

        return $docs;
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
            $fieldData = $this->getIntrospection()->resolveField($field);
            foreach ($fieldData as $dataField => $options) {
                // Only one field per class, so let's take the fieldData. This will override previous additions
                $this->addField($doc, $item, $options);
                if (array_key_exists($field, $boostFields)) {
                    $doc->setFieldBoost($dataField, $boostFields[$field]);
                }
            }
        }
    }

    /**
     * @param Document $doc
     * @param DataObject $object
     * @param          $field
     *
     * @throws LogicException
     */
    protected function addField($doc, $object, $field): void
    {
        if (!$this->classIs($object, $field['origin'])) {
            return;
        }

        $this->extend('onBeforeAddField', $field);

        try {
            $valuesForField = [DataResolver::identify($object, $field['field'])];
        } catch (Exception $e) {
            $valuesForField = [];
        }

        $typeMap = Statics::getTypeMap();
        $type = $typeMap[$field['type']] ?? $typeMap['*'];

        foreach ($valuesForField as $value) {
            if ($value === null) {
                continue;
            }
            $this->extend('onBeforeAddDoc', $field, $value);
            $this->addToDoc($doc, $field, $type, $value);
        }
    }

    /**
     * Determine if the given object is one of the given type
     * @todo remove in favour of the inheritance check from PHP
     * @param string|array|DataObject $class
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
     * @param Document $doc
     * @param array $field
     * @param string $type
     * @param DBField|string $value
     */
    protected function addToDoc($doc, $field, $type, $value): void
    {
        /* Solr requires dates in the form 1995-12-31T23:59:59Z, so we need to normalize to GMT */
        if ($type === 'tdate' || $value instanceof DBDate) {
            $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
        }

        $name = getShortFieldName($field['name']);

        $doc->addField($name, $value, null, Document::MODIFIER_SET);
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
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
