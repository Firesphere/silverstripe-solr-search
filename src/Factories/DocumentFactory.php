<?php
/**
 * class DocumentFactory|Firesphere\SolrSearch\Factories\DocumentFactory Build a Solarium document to push
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Factories;

use Exception;
use Firesphere\SearchBackend\Factories\DocumentCoreFactory;
use Firesphere\SearchBackend\Helpers\DataResolver;
use Firesphere\SearchBackend\Helpers\Statics;
use Firesphere\SearchBackend\Queries\BaseQuery;
use Firesphere\SearchBackend\Traits\LoggerTrait;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\DocumentFactoryTrait;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use Solarium\QueryType\Update\Query\Document;
use Solarium\QueryType\Update\Query\Query;

/**
 * Class DocumentFactory
 * Factory to create documents to be pushed to Solr
 *
 * @package Firesphere\Solr\Search
 */
class DocumentFactory extends DocumentCoreFactory
{
    use Configurable;
    use Extensible;

    /**
     * @var array Numeral types in Solr
     */
    protected static $numerals = [
        'tint',
        'tfloat',
        'tdouble',
    ];

    /**
     * Note, it can only take one type of class at a time!
     * So make sure you properly loop and set $class
     *
     * @param array $fields Fields to index
     * @param BaseIndex $index Index to push the documents to
     * @param Query $update Update Query object
     * @return array Documents to be pushed
     * @throws Exception
     */
    public function buildItems($fields, $index, $update = null): array
    {
        $this->getFieldResolver()->setIndex($index);
        $boostFields = $index->getBoostedFields();
        $docs = [];
        if ($this->debug) {
            $this->indexGroupMessage($index);
        }

        foreach ($this->getItems() as $item) {
            // Don't index items that should not show in search explicitly.
            // Just a "not" is insufficient, as it could be null or false (both meaning, not set)
            if ($item->ShowInSearch === 0) {
                continue;
            }
            /** @var Document $doc */
            $doc = $update->createDocument();
            $this->addDefaultFields($doc, $item);

            $this->buildFields($fields, $doc, $item, $boostFields);
            $item->destroy();

            $docs[] = $doc;
        }

        return $docs;
    }

    /**
     * Add fields that should always be included
     *
     * @param Document $doc Solr Document
     * @param DataObject|DataObjectExtension $item Item to get the data from
     */
    protected function addDefaultFields($doc, $item)
    {
        $doc->setKey(SolrCoreService::ID_FIELD, $item->ClassName . '-' . $item->ID);
        $doc->addField(SolrCoreService::CLASS_ID_FIELD, $item->ID);
        $doc->addField('ClassName', $item->ClassName);
        $hierarchy = ClassInfo::ancestry($item);
        $classHierarchy = [];
        foreach ($hierarchy as $lower => $camel) {
            $classHierarchy[] = $camel;
        }
        $doc->addField('ClassHierarchy', $classHierarchy);
        $doc->addField('ViewStatus', $item->getViewStatus());
        $this->extend('updateDefaultFields', $doc, $item);
    }

    /**
     * Create the required record for a field
     *
     * @param array $fields Fields to build a record for
     * @param Document $doc Document for Solr
     * @param DataObject $item Object to get the data for
     * @param array $boostFields Custom set of index-time-boosted fields
     * @throws Exception
     */
    protected function buildFields($fields, Document $doc, DataObject $item, array $boostFields): void
    {
        foreach ($fields as $field) {
            $fieldData = $this->getFieldResolver()->resolveField($field);
            foreach ($fieldData as $dataField => $options) {
                $options['boost'] = $boostFields[$field] ?? null;
                $this->addField($doc, $item, $options);
            }
        }
    }

    /**
     * Add a single field to the Solr index
     *
     * @param Document $doc Solr Document
     * @param DataObject $object Object whose field is to be added
     * @param array $options Additional options
     */
    protected function addField($doc, $object, $options): void
    {
        if (!$this->classIs($object, $options['origin'])) {
            return;
        }

        $this->extend('onBeforeAddField', $options);

        $valuesForField = $this->getValuesForField($object, $options);

        $typeMap = Statics::getTypeMap();
        $type = $typeMap[$options['type']] ?? $typeMap['*'];

        foreach ($valuesForField as $value) {
            $this->extend('onBeforeAddDoc', $options, $value);
            $this->addToDoc($doc, $options, $type, $value);
        }
    }

    /**
     * Push field to a document
     *
     * @param Document $doc Solr document
     * @param array $options Custom options
     * @param string $type Type of Solr field
     * @param DBField|string|null $value Value(s) of the field
     */
    protected function addToDoc($doc, $options, $type, $value): void
    {
        /* Solr requires dates in the form 1995-12-31T23:59:59Z, so we need to normalize to GMT */
        if (($value && $type === 'tdate') || $value instanceof DBDate) {
            $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
        }

        $name = getShortFieldName($options['name']);

        $doc->addField($name, $value, $options['boost'], Document::MODIFIER_SET);
    }

    /**
     * Are we debugging?
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set to true if debugging should be enabled
     *
     * @param bool $debug
     * @return DocumentFactory
     */
    public function setDebug(bool $debug): DocumentFactory
    {
        $this->debug = $debug;

        return $this;
    }
}
