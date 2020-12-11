<?php
/**
 * Trait LoggerTrait|Firesphere\SolrSearch\Traits\LoggerTrait Trait to help getting and setting the logger
 *
 * @package Firesphere\SolrSearch
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Trait LoggerTrait
 *
 * Trait for getting and setting the logger.
 *
 * @package Firesphere\SolrSearch
 */
trait LoggerTrait
{
    /**
     * The logger to use
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = Injector::inst()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    /**
     * Set the logger if needed
     *
     * @param LoggerInterface $logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }
}
