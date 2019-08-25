<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;

class ConfigStoreTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @expectedException \Solarium\Exception\RuntimeException
     */
    public function testFileConstruct()
    {
        new FileConfigStore([]);
    }

    public function testFileConstructGood()
    {
        $store = new FileConfigStore(['path' => 'test']);
        $this->assertEquals(Director::baseFolder() . '/test/lol', $store->instanceDir('lol'));

        $this->assertEquals(['path' => Director::baseFolder() . '/test'], $store->getConfig());
    }

    /**
     * @expectedException \Solarium\Exception\RuntimeException
     */
    public function testPostConstruct()
    {
        new PostConfigStore([]);
    }

    public function testPostConstructGood()
    {
        $store = new PostConfigStore(['path' => 'test']);

        $this->assertEquals('test/', $store->getPath());

        $this->assertEquals('lol', $store->instanceDir('lol'));
    }

    public function testPostConstructNoPath()
    {
        $store = new PostConfigStore(['uri' => 'http://test.com', 'test' => 'this']);

        $this->assertEquals('/', $store->getPath());
    }

    /**
     * @expectedException \LogicException
     */
    public function testPostConstructNoURI()
    {
        new PostConfigStore(['test' => 'this']);
    }

    public function testPostFile()
    {
        $store = new PostConfigStore(['uri' => 'http://localhost', 'path' => 'test']);

        $dummyData = json_encode(['success' => true]);
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], $dummyData),
            new Response(200, ['X-Foo' => 'Bar'], $dummyData),
        ]);

        $fileUpload = $store->uploadFile('TestIndex', __DIR__ . '/../fixtures/solrResponse.json', $mock)->getBody();
        $this->assertEquals($dummyData, $fileUpload);
        $stringUpload = $store->uploadString('TestIndex', 'solr.xml', '<xml>test</xml>', $mock)->getBody();
        $this->assertEquals($dummyData, $stringUpload);
    }
}
