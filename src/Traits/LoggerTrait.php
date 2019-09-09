<?php


namespace Firesphere\SolrSearch\Traits;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Trait LoggerTrait
 *
 * @package Firesphere\SolrSearch\Traits
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
