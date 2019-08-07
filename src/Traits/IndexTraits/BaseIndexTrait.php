<?php


namespace Firesphere\SolrSearch\Traits;

use SilverStripe\Dev\Deprecation;
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
     * @param string $field Name of the copyfield
     * @param array $options Array of all fields that should be copied to this copyfield
     * @return $this
     */
    public function addCopyField($field, $options): self
    {
        $this->copyFields[$field] = $options;

        if (!in_array($field, $this->getFulltextFields(), true)) {
            $this->addFulltextField($field);
        }

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
}
