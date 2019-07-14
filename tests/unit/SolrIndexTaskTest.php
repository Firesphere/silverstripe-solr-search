<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class SolrIndexTaskTest extends SapphireTest
{
    public function testRun()
    {
        $getVars = [
            'group' => 0,
            'index' => 'CircleCITestIndex'
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);

        $result = $task->run($request);

        $this->assertEquals(0, $result);

        $getVars = [
            'group' => 0,
            'index' => 'CircleCITestIndex',
            'clear' => 1
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);

        $result = $task->run($request);

        $this->assertEquals(0, $result);
        $getVars = [
            'start' => 1,
            'index' => 'CircleCITestIndex',
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);

        $result = $task->run($request);

        $this->assertEquals(0, $result);
    }

    public function testGetLogger()
    {
        $task = new SolrIndexTask();

        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());
        $task->setLogger(null);
        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());
    }
}
