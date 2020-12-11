<?php
/**
 * Trait GetSetSearchResolverTrait|Firesphere\SolrSearch\Traits\GetSetSearchResolverTrait Used to extract methods from
 * the {@link \Firesphere\SolrSearch\Helpers\FieldResolver} to make the code more readable
 *
 * @package Firesphere\SolrSearch
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;

/**
 * Setters and getters for the introspection.
 *
 * Setters and getters to help with introspection/resolving, it's fairly simple, but extracted
 * so it's cleaner to read the code
 *
 * @package Firesphere\SolrSearch
 */
trait GetSetSearchResolverTrait
{
    /**
     * @var BaseIndex Index to use
     */
    protected $index;

    /**
     * Get the current index
     *
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * Set the current index
     *
     * @param mixed $index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }
}
