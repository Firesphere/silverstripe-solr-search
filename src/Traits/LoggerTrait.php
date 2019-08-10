<?php


namespace Firesphere\SolrSearch\Traits;


use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

trait LoggerTrait
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @return mixed|LoggerInterface|null
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = Injector::inst()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

}