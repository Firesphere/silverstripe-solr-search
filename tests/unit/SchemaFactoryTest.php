<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Factories\SchemaFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;

class SchemaFactoryTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var SchemaFactory
     */
    protected $factory;

    public function testGetSetIndex()
    {
        $index = new TestIndex();

        $this->assertNull($this->factory->getIndex());
        $this->factory->setIndex($index);
        $this->assertInstanceOf(BaseIndex::class, $this->factory->getIndex());
    }

    public function testGettersSetters()
    {
        $index = new TestIndex();

        $this->factory->setIndex($index);
        $this->assertEquals($index->getIndexName(), $this->factory->getIndexName());

        $this->assertEquals($index->getDefaultField(), $this->factory->getDefaultField());
    }

    public function testGetSetSchemaLocations()
    {
        $existing = $this->factory->getBaseTemplatePath('schema');
        $basePath = SolrCoreService::config()->get('paths');
        $copy = $basePath;

        $copy['base_path'] = '%s/app';

        Config::modify()->set(SolrCoreService::class, 'paths', $copy);

        $factory = new SchemaFactory();

        $this->assertFalse($factory->getBaseTemplatePath('schema'));

        $original = $factory->getTemplatePathFor('schema');

        $this->assertContains('/app/', $factory->getTemplatePathFor('schema'));

        // Second run should hit the template already existing
        $this->assertEquals($original, $factory->getTemplatePathFor('schema'));
        $this->assertEquals($original, $factory->getBaseTemplatePath('schema'));
        $this->assertNotEquals(
            $existing,
            $factory->getBaseTemplatePath('schema')
        );

        // Reset the config to what it was
        Config::modify()->set(SolrCoreService::class, 'paths.base_path', $basePath);
    }

    protected function setUp()
    {
        $this->factory = Injector::inst()->get(SchemaFactory::class);
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }

        parent::setUp();
    }
}
