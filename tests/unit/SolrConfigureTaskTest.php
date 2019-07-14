<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Psr\Log\LoggerInterface;
use SilverStripe\Dev\SapphireTest;

class SolrConfigureTaskTest extends SapphireTest
{
    public function testGetLogger()
    {
        $task = new SolrConfigureTask();

        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());
    }
}
