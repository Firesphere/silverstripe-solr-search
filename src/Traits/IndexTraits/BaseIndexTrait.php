<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use ReflectionClass;
use ReflectionException;
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
 * This is slightly cheating, but it works and also makes things more readable.
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait BaseIndexTrait
{
    /**
     * @var Client Query client
     */
    protected $client;
    /**
     * @var array Facet fields
     */
    protected $facetFields = [];
    /**
     * @var array Fulltext fields
     */
    protected $fulltextFields = [];
    /**
     * @var array Filterable fields
     */
    protected $filterFields = [];
    /**
     * @var array Sortable fields
     */
    protected $sortFields = [];
    /**
     * @var string Default search field
     */
    protected $defaultField = '_text';
    /**
     * @var array Stored fields
     */
    protected $storedFields = [];
    /**
     * @var array Fields to copy to the default fields
     */
    protected $copyFields = [
        '_text' => [
            '*',
        ],
    ];
    /**
     * usedAllFields is used to determine if the addAllFields method has been called
     * This is to prevent a notice if there is no yml.
     *
     * @var bool
     */
    protected $usedAllFields = false;
    /**
     * Return the copy fields
     *
     * @return array
     */
    public function getCopyFields(): array
    {
        return $this->copyFields;
    }

    /**
     * Set the copy fields
     *
     * @param array $copyField
     * @return $this
     */
    public function setCopyFields($copyField): self
    {
        $this->copyFields = $copyField;

        return $this;
    }

    /**
     * Return the default field for this index
     *
     * @return string
     */
    public function getDefaultField(): string
    {
        return $this->defaultField;
    }

    /**
     * Set the default field for this index
     *
     * @param string $defaultField
     * @return $this
     */
    public function setDefaultField($defaultField): self
    {
        $this->defaultField = $defaultField;

        return $this;
    }

    /**
     * Add a field to sort on
     *
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
     * Get the fulltext fields
     *
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
     * Set the fulltext fields
     *
     * @param array $fulltextFields
     * @return $this
     */
    public function setFulltextFields($fulltextFields): self
    {
        $this->fulltextFields = $fulltextFields;

        return $this;
    }

    /**
     * Get the filter fields
     *
     * @return array
     */
    public function getFilterFields(): array
    {
        return $this->filterFields;
    }

    /**
     * Set the filter fields
     *
     * @param array $filterFields
     * @return $this
     */
    public function setFilterFields($filterFields): self
    {
        $this->filterFields = $filterFields;

        return $this;
    }

    /**
     * Add a single Fulltext field
     *
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
     *
     * @param string $field
     * @param array|int $options
     * @param null|int $boost
     * @return mixed
     */
    abstract public function addBoostedField($field, $options = [], $boost = null);

    /**
     * Get the sortable fields
     *
     * @return array
     */
    public function getSortFields(): array
    {
        return $this->sortFields;
    }

    /**
     * Set/override the sortable fields
     *
     * @param array $sortFields
     * @return $this
     */
    public function setSortFields($sortFields): self
    {
        $this->sortFields = $sortFields;

        return $this;
    }

    /**
     * Add a Fulltext Field
     *
     * @param bool $includeSubclasses Compatibility mode, not actually used
     * @throws ReflectionException
     * @deprecated Please use addAllFulltextFields(). IncludeSubClasses is not used anymore
     */
    public function addFulltextFields($includeSubclasses = true)
    {
        $this->addAllFulltextFields();
    }

    /**
     * Add all text-type fields to the given index
     *
     * @throws ReflectionException
     */
    public function addAllFulltextFields()
    {
        $this->addAllFieldsByType(DBString::class);
    }

    /**
     * Add all database-backed text fields as fulltext searchable fields.
     *
     * For every class included in the index, examines those classes and all parent looking for "DBText" database
     * fields (Varchar, Text, HTMLText, etc) and adds them all as fulltext searchable fields.
     *
     * Note, there is no check on boosting etc. That needs to be done manually.
     *
     * @param string $dbType
     * @throws ReflectionException
     */
    protected function addAllFieldsByType($dbType = DBString::class): void
    {
        $this->usedAllFields = true;
        $classes = $this->getClasses();
        foreach ($classes as $key => $class) {
            $fields = DataObject::getSchema()->databaseFields($class, true);

            $this->addFulltextFieldsForClass($fields, $dbType);
        }
    }

    /**
     * This trait requires classes to be set, so getClasses can be called.
     *
     * @return array
     */
    abstract public function getClasses(): array;

    /**
     * Add all fields of a given type to the index
     *
     * @param array $fields The fields on the DataObject
     * @param string $dbType Class type the reflection should extend
     * @throws ReflectionException
     */
    protected function addFulltextFieldsForClass(array $fields, $dbType = DBString::class): void
    {
        foreach ($fields as $field => $type) {
            $pos = strpos($type, '(');
            if ($pos !== false) {
                $type = substr($type, 0, $pos);
            }
            $conf = Config::inst()->get(Injector::class, $type);
            $ref = new ReflectionClass($conf['class']);
            if ($ref->isSubclassOf($dbType)) {
                $this->addFulltextField($field);
            }
        }
    }

    /**
     * Add all date-type fields to the given index
     *
     * @throws ReflectionException
     */
    public function addAllDateFields()
    {
        $this->addAllFieldsByType(DBDate::class);
    }

    /**
     * Add a facet field
     *
     * @param $field
     * @param array $options
     * @return $this
     */
    public function addFacetField($field, $options): self
    {
        $this->facetFields[$field] = $options;

        if (!in_array($options['Field'], $this->getFilterFields(), true)) {
            $this->addFilterField($options['Field']);
        }

        return $this;
    }

    /**
     * Add a filterable field
     *
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
     * Add a copy field
     *
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
     * Add a stored/fulltext field
     *
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
     * Get the client
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set/override the client
     *
     * @param Client $client
     * @return $this
     */
    public function setClient($client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get the stored field list
     *
     * @return array
     */
    public function getStoredFields(): array
    {
        return $this->storedFields;
    }

    /**
     * Set/override the stored field list
     *
     * @param array $storedFields
     * @return BaseIndex
     */
    public function setStoredFields(array $storedFields): self
    {
        $this->storedFields = $storedFields;

        return $this;
    }
}
