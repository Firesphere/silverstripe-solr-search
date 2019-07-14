<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\SapphireTest;

class ConfigStoreTest extends SapphireTest
{

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
        $store = new PostConfigStore(['test' => 'this']);

        $this->assertEquals('/', $store->getPath());
    }

    public function testPostUploadString()
    {
        $store = new PostConfigStore(['uri' => 'http://localhost', 'path' => 'solrconfig/configure']);

        $response = $store->uploadString('testing', 'test.txt', 'this, is, a, test');

        $this->assertInstanceOf(HTTPResponse::class, $response);
    }
}
