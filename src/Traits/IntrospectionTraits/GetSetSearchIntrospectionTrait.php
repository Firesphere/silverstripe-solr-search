<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;

trait GetSetSearchIntrospectionTrait
{
    /**
     * @var BaseIndex
     */
    protected $index;
    /**
     * @var array
     */
    protected $found = [];

    /**
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * @param mixed $index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return array
     */
    public function getFound(): array
    {
        return $this->found;
    }
}
