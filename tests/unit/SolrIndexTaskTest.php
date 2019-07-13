<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Tasks\SolrIndexTask;
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
            'clear' => true
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);

        $result = $task->run($request);

        $this->assertEquals(0, $result);
    }
}