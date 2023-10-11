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

    public function testFileConstruct()
    {
        $this->expectException(\Solarium\Exception\RuntimeException::class);
        new FileConfigStore([]);
    }

    public function testFileConstructGood()
    {
        $store = new FileConfigStore(['uri' => 'http://test.com', 'path' => 'test']);
        $this->assertEquals(Director::baseFolder() . '/test/lol', $store->instanceDir('lol'));

        $this->assertEquals(
            [
                'path' => Director::baseFolder() . '/test',
                'uri'  => 'http://test.com',
            ],
            $store->getConfig()
        );
    }

    public function testFileConfigPath()
    {
        $this->expectException(\RuntimeException::class);
        $config = ['path' => '/sys'];
        $store = new FileConfigStore($config);

        $store->getTargetDir('CircleCITestIndex');
    }

    public function testPostConstruct()
    {
        $this->expectException(\RuntimeException::class);
        new PostConfigStore([]);
    }

    public function testPostConstructGood()
    {
        $store = new PostConfigStore(['uri' => 'http://test.com', 'path' => 'test']);

        $this->assertEquals('test/', $store->getPath());

        $this->assertEquals('lol', $store->instanceDir('lol'));
    }

    public function testPostConstructNoPath()
    {
        $store = new PostConfigStore(['uri' => 'http://test.com', 'test' => 'this']);

        $this->assertEquals('/', $store->getPath());
    }

    public function testPostConstructNoURI()
    {
        $this->expectException(\LogicException::class);
        new PostConfigStore(['test' => 'this']);
    }

    public function testPostFile()
    {
        $store = new PostConfigStore(['uri' => 'http://localhost', 'path' => 'test']);

        $dummyData = json_encode(['success' => true]);
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], $dummyData),
            new Response(200, ['X-Foo' => 'Bar'], $dummyData),
            new Response(200, ['X-Foo' => 'Bar'], $dummyData),
        ]);

        $fileUpload = $store->uploadFile('TestIndex', __DIR__ . '/../fixtures/solrResponse.json', $mock)->getBody();
        $this->assertEquals($dummyData, $fileUpload);
        $stringUpload = $store->uploadString('TestIndex', 'solr.xml', '<xml>test</xml>', $mock)->getBody();
        $this->assertEquals($dummyData, $stringUpload);
        $store = new PostConfigStore([
            'uri'  => 'http://localhost',
            'path' => 'test',
            'auth' => [
                'username' => 'test',
                'password' => 'test',
            ],
        ]);

        $stringUpload = $store->uploadString('TestIndex', 'solr.xml', '<xml>test</xml>', $mock)->getBody();
        $this->assertEquals($dummyData, $stringUpload);
    }
}
