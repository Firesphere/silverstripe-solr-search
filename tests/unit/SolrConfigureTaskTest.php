<?php


namespace Firesphere\SolrSearch\Tests;

use Exception;
use Firesphere\PartialUserforms\Tests\TestHelper;
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

    /**
     * @covers \Firesphere\SolrSearch\Tasks\SolrConfigureTask::logException
     */
    public function testLogException()
    {
        ob_start();
        $exception = new Exception('Test exception');
        $task = new SolrConfigureTask();
        TestHelper::invokeMethod($task, 'logException', ['CircleCITestIndex', $exception]);

        $expected = 'Error loading core CircleCITestIndex';
        $this->assertContains($expected, ob_get_clean());

        ob_end_clean();
    }
}
