<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * Trait DocumentFactoryTrait is a basic getter setter for the DocumentFactory.
 *
 * Getter and setter helpers
 * @package Firesphere\SolrSearch\Traits
 */
trait DocumentFactoryTrait
{
    /**
     * @var FieldResolver Resolver for fields
     */
    protected $fieldResolver;
    /**
     * @var null|ArrayList|DataList Items to create documents for
     */
    protected $items;
    /**
     * @var string Current class that's being indexed
     */
    protected $class;

    /**
     * Current class being indexed
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Set the current class to be indexed
     * @param string $class
     * @return DocumentFactory
     */
    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Get the FieldResolver class
     * @return FieldResolver
     */
    public function getFieldResolver(): FieldResolver
    {
        return $this->fieldResolver;
    }

    /**
     * Get the items being indexed
     * @return ArrayList|DataList|null
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the items to index
     * @param ArrayList|DataList|null $items
     * @return DocumentFactory
     */
    public function setItems($items): self
    {
        $this->items = $items;

        return $this;
    }
}
