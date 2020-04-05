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
    protected $fieldResolver;

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

        $test = FieldResolver::getHierarchy(Page::class, false);
        $this->assertEquals($expected, $test);
        $test2 = FieldResolver::getHierarchy(Page::class, true);
        unset($expected[1]); // Page::class does not have data fields
        $this->assertEquals(array_values($expected), $test2);
    }

    public function testGetFieldIntrospection()
    {
//        $this->markTestSkipped('Currently broken, needs investigation as to why');
        $index = new CircleCITestIndex();

        $factory = new FieldResolver();
        $factory->setIndex($index);
        $expected = [
            SiteTree::class . '_Content' =>
                [
                    'name'         => SiteTree::class . '_Content',
                    'field'        => 'Content',
                    'origin'       => SiteTree::class,
                    'type'         => 'HTMLText',
                    'multi_valued' => false,
                    'fullfield'    => 'Content',
                    'class'        => SiteTree::class,
                ],
        ];

        $result = $factory->resolveField('Content');

        $this->assertEquals($expected, $result);
        $index = new TestIndexTwo();

        $factory->setIndex($index);

        $expected = [
            SiteTree::class . '_TestObject_TestRelation_Title' =>
                [
                    'name'         => SiteTree::class . '_TestObject_TestRelation_Title',
                    'field'        => 'Title',
                    'fullfield'    => 'TestObject_TestRelation_Title',
                    'origin'       => SiteTree::class,
                    'class'        => TestRelationObject::class,
                    'type'         => 'Varchar',
                    'multi_valued' => true,
                ],
        ];

        $this->assertEquals($expected, $factory->resolveField('TestObject.TestRelation.Title'));

        $expected = [
            SiteTree::class . '_RelationObject_Title' =>
                [
                    'name'         => SiteTree::class . '_RelationObject_Title',
                    'field'        => 'Title',
                    'fullfield'    => 'RelationObject_Title',
                    'origin'       => SiteTree::class,
                    'class'        => TestRelationObject::class,
                    'type'         => 'Varchar',
                    'multi_valued' => true,
                ],
        ];

        $this->assertEquals($expected, $factory->resolveField('RelationObject.Title'));
    }

    protected function setUp()
    {
        $this->fieldResolver = new FieldResolver();
        $this->fieldResolver->setIndex(new CircleCITestIndex());

        return parent::setUp();
    }
}
