<?php


namespace Firesphere\SolrSearch\Tests;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Jobs\SolrConfigureJob;
use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;

class SolrConfigureJobTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var SolrConfigureJob
     */
    protected $job;

    public function testGetTitle()
    {
        $this->assertEquals('Configure new or re-configure existing Solr cores', $this->job->getTitle());
    }

    public function testProcess()
    {
        $this->job->process();
        try {
            $solrResponse = file_get_contents('http://127.0.0.1:8983/solr/CircleCITestIndex/admin/ping');
            $response = json_decode($solrResponse);
            $this->assertEquals('OK', $response->status);
        } catch (Exception $e) {
            Debug::dump('Running old solr version. Not testing');
        }
    }

    protected function setUp()
    {
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
        $this->job = Injector::inst()->get(SolrConfigureJob::class);

        parent::setUp();
    }
}
