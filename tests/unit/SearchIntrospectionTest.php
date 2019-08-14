<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Page;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ErrorPage\ErrorPage;

class SearchIntrospectionTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    public function testIsSubclassOf()
    {
        $this->assertTrue(SearchIntrospection::isSubclassOf(Page::class, [SiteTree::class, Page::class]));
        $this->assertFalse(SearchIntrospection::isSubclassOf(ModelAdmin::class, [SiteTree::class, Page::class]));
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

        $test = SearchIntrospection::getHierarchy(Page::class, true, false);
        $this->assertEquals($expected, $test);
        $test2 = SearchIntrospection::getHierarchy(Page::class, false, true);
        $this->assertEquals([SiteTree::class], $test2);
        $test3 = SearchIntrospection::getHierarchy(Page::class, false, false);
        $this->assertEquals([SiteTree::class, Page::class], $test3);
    }

    public function testGetFieldIntrospection()
    {
        $index = new CircleCITestIndex();

        $factory = new SearchIntrospection();
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

        $result = $factory->getFieldIntrospection('Content');
        $this->assertEquals($expected, $result);
        $found = $factory->getFound();

        $this->assertEquals($expected, $found[SiteTree::class . '_Content']);
    }

    protected function setUp()
    {
        $this->introspection = new SearchIntrospection();
        $this->introspection->setIndex(new CircleCITestIndex());

        return parent::setUp();
    }
}
