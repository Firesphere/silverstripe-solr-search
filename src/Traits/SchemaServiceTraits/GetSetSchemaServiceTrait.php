<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;

/**
 * Trait GetSetSchemaServiceTrait
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait GetSetSchemaServiceTrait
{
    /**
     * @var string ABSOLUTE Path to template
     */
    protected $template;
    /**
     * @var bool
     */
    protected $store = false;
    /**
     * @var BaseIndex
     */
    protected $index;
    /**
     * @var string ABSOLUTE Path to types.ss template
     */
    protected $typesTemplate;

    /**
     * @param bool $store
     */
    public function setStore(bool $store): void
    {
        $this->store = $store;
    }

    /**
     * @return BaseIndex
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param BaseIndex $index
     * @return SchemaService
     */
    public function setIndex($index): self
    {
        $this->index = $index;
        // Add the index to the introspection as well, there's no need for a separate call here
        // We're loading this core, why would we want the introspection from a different index?
        $this->introspection->setIndex($index);

        return $this;
    }

    /**
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->index->getIndexName();
    }

    /**
     * @return string|array
     */
    public function getDefaultField()
    {
        return $this->index->getDefaultField();
    }

    /**
     * Get the Identifier Field for Solr
     *
     * @return string
     */
    public function getIDField(): string
    {
        return SolrCoreService::ID_FIELD;
    }

    /**
     * Get the Identifier Field for Solr
     *
     * @return string
     */
    public function getClassID(): string
    {
        return SolrCoreService::CLASS_ID_FIELD;
    }

    /**
     * @return string
     */
    public function getTypesTemplate()
    {
        return $this->typesTemplate;
    }

    /**
     * @param string $typesTemplate
     * @return SchemaService
     */
    public function setTypesTemplate($typesTemplate): self
    {
        $this->typesTemplate = $typesTemplate;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return SchemaService
     */
    public function setTemplate($template): self
    {
        $this->template = $template;

        return $this;
    }
}
