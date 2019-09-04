<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;

/**
 * Trait GetSetSearchIntrospectionTrait
 * @package Firesphere\SolrSearch\Traits
 */
trait GetSetSearchIntrospectionTrait
{
    /**
     * @var BaseIndex Index to use
     */
    protected $index;
    /**
     * @var array Found items
     */
    protected $found = [];

    /**
     * Get the current index
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * Set the current index
     * @param mixed $index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Get whatever is found
     * @return array
     */
    public function getFound(): array
    {
        return $this->found;
    }
}
