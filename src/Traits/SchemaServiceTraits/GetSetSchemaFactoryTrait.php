<?php
/**
 * Trait GetSetSchemaFactoryTrait|Firesphere\SolrSearch\Traits\GetSetSchemaFactoryTrait Getters and setters
 * for {@link \Firesphere\SolrSearch\Factories\SchemaFactory}
 *
 * @package Firesphere\SolrSearch\Traits
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Factories\SchemaFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;

/**
 * Trait GetSetSchemaFactoryTrait
 *
 * Getters and setters for the schema factory
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait GetSetSchemaFactoryTrait
{
    /**
     * ABSOLUTE Path to template
     *
     * @var string
     */
    protected $template;
    /**
     * Store the value in Solr
     *
     * @var bool
     */
    protected $store = false;
    /**
     * Index to generate the schema for
     *
     * @var BaseIndex
     */
    protected $index;
    /**
     * ABSOLUTE Path to types.ss template
     *
     * @var string
     */
    protected $typesTemplate;

    /**
     * Set the store value
     *
     * @param bool $store
     */
    public function setStore(bool $store): void
    {
        $this->store = $store;
    }

    /**
     * Get the Index that's being used
     *
     * @return BaseIndex
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Set the index that's being used and add the introspection for it
     *
     * @param BaseIndex $index
     * @return SchemaFactory
     */
    public function setIndex($index): self
    {
        $this->index = $index;
        // Add the index to the introspection as well, there's no need for a separate call here
        // We're loading this core, why would we want the introspection from a different index?
        $this->fieldResolver->setIndex($index);

        return $this;
    }

    /**
     * Get the name of the index being used
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->index->getIndexName();
    }

    /**
     * Get the default field to generate df components for
     *
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
     * Get the types template if defined
     *
     * @return string
     */
    public function getTypesTemplate()
    {
        return $this->typesTemplate;
    }

    /**
     * Set custom types template
     *
     * @param string $typesTemplate
     * @return SchemaFactory
     */
    public function setTypesTemplate($typesTemplate): self
    {
        $this->typesTemplate = $typesTemplate;

        return $this;
    }

    /**
     * Get the base template for the schema xml
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set a custom template for schema xml
     *
     * @param string $template
     * @return SchemaFactory
     */
    public function setTemplate($template): self
    {
        $this->template = $template;

        return $this;
    }
}
