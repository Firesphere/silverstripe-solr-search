<?php


namespace Firesphere\SolrSearch\Tests;

use Exception;
use Firesphere\PartialUserforms\Tests\TestHelper;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use PhpCsFixer\Config;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

class SolrIndexTaskTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    protected static $required_extensions = [
        DataObject::class => [DataObjectExtension::class],
    ];

    public function setUp()
    {
        $siteConfig = SiteConfig::current_site_config();
        $siteConfig->CanViewType = 'Anyone';
        $siteConfig->write();
        parent::setUp();
    }

    public function testGettersSetters()
    {
        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);
        $cores = SolrCoreService::config()->get('cpucores') ?: 1;
        $index = new TestIndex();
        $this->assertEquals($cores, $task->getCores());

        $task->setCores(10);

        $this->assertEquals(10, $task->getCores());

        $task->setCores($cores);

        $task->setIndex($index);

        $this->assertInstanceOf(TestIndex::class, $task->getIndex());
    }

    public function testRun()
    {
        $getVars = [
            'group'    => 0,
            'index'    => 'CircleCITestIndex',
            'unittest' => 1
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class, true);

        $result = $task->run($request);

        $this->assertEquals(0, $result);

        $getVars = [
            'group'    => 0,
            'index'    => 'CircleCITestIndex',
            'clear'    => 1,
            'unittest' => 1
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        $result = $task->run($request);

        $this->assertEquals(0, $result);
        $getVars = [
            'start'    => 0,
            'index'    => 'CircleCITestIndex',
            'unittest' => 1
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        $result = $task->run($request);

        $this->assertEquals(0, $result);
    }

    /**
     * This test is specifically to test that the task will run fine with PCNTL.
     * In the index task, there's a check if the 'unittest' flag is set, to throw an exception.
     * It's a workaround for `exit()` which kills the entire PHPUnit process, no matter the PCNTL state.
     *
     * @expectedException \Exception
     */
    public function testRunPCNTL()
    {
        $this->markTestSkipped('PCNTL Is not available on CircleCI yet');
        $getVars = [
            'group'    => 0,
            'index'    => 'CircleCITestIndex',
            'unittest' => 'pcntl'
        ];
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', $getVars);

        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class, true);

        $task->run($request);
    }

    public function testGetLogger()
    {
        $task = new SolrIndexTask();

        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());
        $task->setLogger(null);
        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());
    }

    /**
     * @covers \Firesphere\SolrSearch\Tasks\SolrIndexTask::logException
     */
    public function testLogException()
    {
        ob_start();
        $exception = new Exception('Test exception');
        $task = new SolrIndexTask();
        TestHelper::invokeMethod($task, 'logException', ['CircleCITestIndex', 2, $exception]);

        $expected = 'Error indexing core CircleCITestIndex on group 2';
        $this->assertContains($expected, ob_get_clean());

        ob_end_clean();
    }
}
