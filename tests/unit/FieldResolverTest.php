<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use Page;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ErrorPage\ErrorPage;

class FieldResolverTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var FieldResolver
     */
    protected $introspection;

    public function testIsSubclassOf()
    {
        $this->assertTrue(FieldResolver::isSubclassOf(Page::class, [SiteTree::class, Page::class]));
        $this->assertFalse(FieldResolver::isSubclassOf(ModelAdmin::class, [SiteTree::class, Page::class]));
    }

    public function testHierarchy()
    {
        // Expected Hierarchy is all pagetypes, including the test ones
        $expected = [
            SiteTree::class,
            Page::class,
            TestPage::class,
            ErrorPage::class,
            RedirectorPage::class,
            VirtualPage::class,
        ];

        $test = FieldResolver::getHierarchy(Page::class, true, false);
        $this->assertEquals($expected, $test);
        $test2 = FieldResolver::getHierarchy(Page::class, false, true);
        $this->assertEquals([SiteTree::class], $test2);
        $test3 = FieldResolver::getHierarchy(Page::class, false, false);
        $this->assertEquals([SiteTree::class, Page::class], $test3);
    }

    public function testGetFieldIntrospection()
    {
        $index = new CircleCITestIndex();

        $factory = new FieldResolver();
        $factory->setIndex($index);
        $expected = [
            SiteTree::class . '_Content' =>
                [
                    'name'         => SiteTree::class . '_Content',
                    'field'        => 'Content',
                    'fullfield'    => 'Content',
                    'origin'       => SiteTree::class,
                    'class'        => SiteTree::class,
                    'type'         => 'HTMLText',
                    'multi_valued' => false,
                ],
        ];

        $result = $factory->resolveField('Content');
        $this->assertEquals($expected, $result);
        $found = $factory->getFound();

        $this->assertEquals($expected, $found[SiteTree::class . '_Content']);
    }

    protected function setUp()
    {
        $this->introspection = new FieldResolver();
        $this->introspection->setIndex(new CircleCITestIndex());

        return parent::setUp();
    }
}
