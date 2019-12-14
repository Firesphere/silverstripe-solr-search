<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;

/**
 * Trait SolrIndexTrait
 * Getters and Setters for the SolrIndexTask
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait SolrIndexTrait
{
    /**
     * Debug mode enabled, default false
     *
     * @var bool
     */
    protected $debug = false;
    /**
     * Singleton of {@link SolrCoreService}
     *
     * @var SolrCoreService
     */
    protected $service;
    /**
     * @var BaseIndex Current core being indexed
     */
    protected $index;
    /**
     * @var int Number of CPU cores available
     */
    protected $cores = 1;
    /**
     * Default batch length
     *
     * @var int
     */
    protected $batchLength = 1;

    /**
     * Set the {@link SolrCoreService}
     *
     * @param SolrCoreService $service
     * @return self
     */
    public function setService(SolrCoreService $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * @param BaseIndex $index
     */
    public function setIndex(BaseIndex $index): void
    {
        $this->index = $index;
    }

    /**
     * @return int
     */
    public function getCores(): int
    {
        return $this->cores;
    }

    /**
     * @param int $cores
     */
    public function setCores(int $cores): void
    {
        $this->cores = $cores;
    }

    /**
     * @return int
     */
    public function getBatchLength(): int
    {
        return $this->batchLength;
    }

    /**
     * @param int $batchLength
     */
    public function setBatchLength(int $batchLength): void
    {
        $this->batchLength = $batchLength;
    }
}
