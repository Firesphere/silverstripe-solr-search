<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
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
        $this->assertEquals('test/lol', $store->instanceDir('lol'));

        $this->assertEquals(['path' => 'test'], $store->getConfig());
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
}