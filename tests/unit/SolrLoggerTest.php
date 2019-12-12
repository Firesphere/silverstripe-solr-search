<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Tasks\ClearErrorsTask;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Dev\SapphireTest;

class SolrLoggerTest extends SapphireTest
{
    public function testConstruct()
    {
        $logger = new SolrLogger();

        $this->assertInstanceOf(Client::class, $logger->getClient());
    }

    public function testGetSetClient()
    {
        $logger = new SolrLogger();

        $client = $logger->getClient();
        $this->assertInstanceOf(Client::class, $logger->getClient());
        $logger->setClient($client);
        $this->assertEquals($client, $logger->getClient());
    }

    public function testSaveSolrLog()
    {
        $body = file_get_contents(__DIR__ . '/../fixtures/solrResponse.json');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);

        $logger = new SolrLogger($mock);

        $logger->saveSolrLog('Query');

        $this->assertCount(9, SolrLog::get());

        ob_start();
        $task = new ClearErrorsTask();
        $request = new NullHTTPRequest();
        $task->run($request);
        ob_end_flush();

        $this->assertCount(0, SolrLog::get());
    }

    public function testLogMessage()
    {
        ob_start();
        SolrLogger::logMessage('Query', 'AwesomeTest', 'CircleCITestIndex');
        $output = ob_get_contents();
        $this->assertContains(
            'AwesomeTest',
            $output
        );
        ob_end_clean();
    }
}
