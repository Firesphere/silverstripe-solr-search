<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBString;
use Solarium\Core\Client\Client;

/**
 * This is slightly cheating, but it works and also makes things more readable.
 *
 * Trait BaseIndexTrait
 * @package Firesphere\SolrSearch\Traits
 */
trait BaseIndexTrait
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $facetFields = [];
    /**
     * @var array
     */
    protected $fulltextFields = [];
    /**
     * @var array
     */
    protected $filterFields = [];
    /**
     * @var array
     */
    protected $sortFields = [];
    /**
     * @var string
     */
    protected $defaultField = '_text';
    /**
     * @var array
     */
    protected $storedFields = [];
    /**
     * @var array
     */
    protected $copyFields = [
        '_text' => [
            '*'
        ],
    ];

    /**
     * @return array
     */
    public function getCopyFields(): array
    {
        return $this->copyFields;
    }

    /**
     * @param array $copyField
     * @return $this
     */
    public function setCopyFields($copyField): self
    {
        $this->copyFields = $copyField;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultField(): string
    {
        return $this->defaultField;
    }

    /**
     * @param string $defaultField
     * @return $this
     */
    public function setDefaultField($defaultField): self
    {
        $this->defaultField = $defaultField;

        return $this;
    }

    /**
     * @param $sortField
     * @return $this
     */
    public function addSortField($sortField): self
    {
        if (!in_array($sortField, $this->getFulltextFields(), true) &&
            !in_array($sortField, $this->getFilterFields(), true)
        ) {
            $this->addFulltextField($sortField);
            $this->sortFields[] = $sortField;
        }

        $this->setSortFields(array_unique($this->getSortFields()));

        return $this;
    }

    /**
     * @return array
     */
    public function getFulltextFields(): array
    {
        return array_values(
            array_unique(
                $this->fulltextFields
            )
        );
    }

    /**
     * @param array $fulltextFields
     * @return $this
     */
    public function setFulltextFields($fulltextFields): self
    {
        $this->fulltextFields = $fulltextFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilterFields(): array
    {
        return $this->filterFields;
    }

    /**
     * @param array $filterFields
     * @return $this
     */
    public function setFilterFields($filterFields): self
    {
        $this->filterFields = $filterFields;

        return $this;
    }

    /**
     * @param string $fulltextField
     * @param null|string $forceType
     * @param array $options
     * @return $this
     */
    public function addFulltextField($fulltextField, $forceType = null, $options = []): self
    {
        if ($forceType) {
            Deprecation::notice('5.0', 'ForceType should be handled through casting');
        }

        $key = array_search($fulltextField, $this->getFilterFields(), true);

        if (!$key) {
            $this->fulltextFields[] = $fulltextField;
        }

        if (isset($options['boost'])) {
            $this->addBoostedField($fulltextField, [], $options['boost']);
        }

        if (isset($options['stored'])) {
            $this->storedFields[] = $fulltextField;
        }

        return $this;
    }

    /**
     * Add an abstract for the add Boosted Field to keep things consistent
     * @param string $field
     * @param array|int $options
     * @param null|int $boost
     * @return mixed
     */
    abstract public function addBoostedField($field, $options = [], $boost = null);

    /**
     * @return array
     */
    public function getSortFields(): array
    {
        return $this->sortFields;
    }

    /**
     * @param array $sortFields
     * @return $this
     */
    public function setSortFields($sortFields): self
    {
        $this->sortFields = $sortFields;

        return $this;
    }

    /**
     * @param bool $includeSubclasses
     * @throws \ReflectionException
     * @deprecated Please use addAllFulltextFields(). IncludeSubClasses is not used anymore
     */
    public function addFulltextFields($includeSubclasses = true)
    {
        $this->addAllFulltextFields();
    }

    /**
     * Add all database-backed text fields as fulltext searchable fields.
     *
     * For every class included in the index, examines those classes and all parent looking for "DBText" database
     * fields (Varchar, Text, HTMLText, etc) and adds them all as fulltext searchable fields.
     *
     * Note, there is no check on boosting etc. That needs to be done manually.
     * @param string $dbType
     * @throws \ReflectionException
     */
    protected function addAllFieldsByType($dbType = DBString::class): void
    {
        $classes = $this->getClasses();
        foreach ($classes as $key => $class) {
            $fields = DataObject::getSchema()->databaseFields($class, true);

            $this->addFulltextFieldsForClass($fields, $dbType);
        }
    }

    /**
     * Add all text-type fields to the given index
     * @throws \ReflectionException
     */
    public function addAllFulltextFields()
    {
        $this->addAllFieldsByType(DBString::class);
    }

    /**
     * Add all date-type fields to the given index
     * @throws \ReflectionException
     */
    public function addAllDateFields()
    {
        $this->addAllFieldsByType(DBDate::class);
    }

    /**
     * Add all fields of a given type to the index
     * @param array $fields The fields on the DataObject
     * @param string $dbType Class type the reflection should extend
     * @throws \ReflectionException
     */
    protected function addFulltextFieldsForClass(array $fields, $dbType = DBString::class): void
    {
        foreach ($fields as $field => $type) {
            $pos = strpos($type, '(');
            if ($pos !== false) {
                $type = substr($type, 0, $pos);
            }
            $conf = Config::inst()->get(Injector::class, $type);
            $ref = new \ReflectionClass($conf['class']);
            if ($ref->isSubclassOf($dbType)) {
                $this->addFulltextField($field);
            }
        }
    }

    /**
     * @param $field
     * @param array $options
     * @return $this
     */
    public function addFacetField($field, $options): self
    {
        $this->facetFields[$field] = $options;

        if (!in_array($field, $this->getFilterFields(), true)) {
            $this->addFilterField($field);
        }

        return $this;
    }

    /**
     * @param $filterField
     * @return $this
     */
    public function addFilterField($filterField): self
    {
        $key = array_search($filterField, $this->getFulltextFields(), true);
        if ($key === false) {
            $this->filterFields[] = $filterField;
        }

        return $this;
    }

    /**
     * @param string $field Name of the copyfield
     * @param array $options Array of all fields that should be copied to this copyfield
     * @return $this
     */
    public function addCopyField($field, $options): self
    {
        $this->copyFields[$field] = $options;

        return $this;
    }

    /**
     * @param string $field
     * @param null|string $forceType
     * @param array $extraOptions
     * @return BaseIndex
     */
    public function addStoredField($field, $forceType = null, $extraOptions = []): self
    {
        $options = array_merge($extraOptions, ['stored' => 'true']);
        $this->addFulltextField($field, $forceType, $options);

        return $this;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return $this
     */
    public function setClient($client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return array
     */
    public function getStoredFields(): array
    {
        return $this->storedFields;
    }

    /**
     * @param array $storedFields
     * @return BaseIndex
     */
    public function setStoredFields(array $storedFields): self
    {
        $this->storedFields = $storedFields;

        return $this;
    }
}
