<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Page;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ViewableData;
use Solarium\Core\Client\Client;
use Solarium\Plugin\BufferedAdd\BufferedAdd;
use Solarium\QueryType\Update\Query\Document;

class DocumentFactoryTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    protected static $required_extensions = [
        DataObject::class => [DataObjectExtension::class],
    ];
    /**
     * We can't use the constant here for unknown reasons
     * If you change the constant, please replace id here with the appropriate value
     *
     * @var array
     */
    protected static $expected_docs = [
        [
            'id'                => 'Page-1',
            'ObjectID'          => 1,
            'ClassName'         => 'Page',
            'ClassHierarchy'    =>
                [
                    'silverstripe\\view\\viewabledata'   => ViewableData::class,
                    'silverstripe\\orm\\dataobject'      => DataObject::class,
                    'silverstripe\\cms\\model\\sitetree' => SiteTree::class,
                    'page'                               => 'Page',
                ],
            'ViewStatus'        =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'    => 'Home',
            'SiteTree_Content'  => '<p>Welcome to SilverStripe! This is the default homepage. ' .
                'You can edit this page by opening <a href="admin/">the CMS</a>.</p>' .
                '<p>You can now access the <a href="http://docs.silverstripe.org">developer documentation</a>,' .
                ' or begin the <a href="http://www.silverstripe.org/learn/lessons">SilverStripe lessons</a>.</p>',
            'SiteTree_ParentID' => 0,
        ],
        [
            'id'                => 'Page-6',
            'ObjectID'          => 6,
            'ClassName'         => 'Page',
            'ClassHierarchy'    =>
                [
                    'silverstripe\\view\\viewabledata'   => ViewableData::class,
                    'silverstripe\\orm\\dataobject'      => DataObject::class,
                    'silverstripe\\cms\\model\\sitetree' => SiteTree::class,
                    'page'                               => 'Page',
                ],
            'ViewStatus'        =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'    => 'Test 1',
            'SiteTree_ParentID' => 1,
        ],
        [
            'id'                  => 'Page-2',
            'ObjectID'            => 2,
            'ClassName'           => 'Page',
            'ClassHierarchy'      =>
                [
                    'silverstripe\\view\\viewabledata'   => ViewableData::class,
                    'silverstripe\\orm\\dataobject'      => DataObject::class,
                    'silverstripe\\cms\\model\\sitetree' => SiteTree::class,
                    'page'                               => 'Page',
                ],
            'ViewStatus'          =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'      => 'About Us',
            'SiteTree_Content'    => '<p>You can fill this page out with your own content, ' .
                'or delete it and create your own pages.</p>',
            'SiteTree_ParentID'   => 0,
            'SiteTree_ViewStatus' => '1-null',
        ],
        [
            'id'                => 'Page-7',
            'ObjectID'          => 7,
            'ClassName'         => 'Page',
            'ClassHierarchy'    =>
                [
                    'silverstripe\\view\\viewabledata'   => ViewableData::class,
                    'silverstripe\\orm\\dataobject'      => DataObject::class,
                    'silverstripe\\cms\\model\\sitetree' => SiteTree::class,
                    'page'                               => 'Page',
                ],
            'ViewStatus'        =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'    => 'Test 2',
            'SiteTree_ParentID' => 1,
        ],
        [
            'id'                => 'Page-3',
            'ObjectID'          => 3,
            'ClassName'         => 'Page',
            'ClassHierarchy'    =>
                [
                    'silverstripe\\view\\viewabledata'   => ViewableData::class,
                    'silverstripe\\orm\\dataobject'      => DataObject::class,
                    'silverstripe\\cms\\model\\sitetree' => SiteTree::class,
                    'page'                               => 'Page',
                ],
            'ViewStatus'        =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'    => 'Contact Us',
            'SiteTree_Content'  => '<p>You can fill this page out with your own content, ' .
                'or delete it and create your own pages.</p>',
            'SiteTree_ParentID' => 0,
        ],
    ];

    public function setUp()
    {
        $siteConfig = SiteConfig::current_site_config();
        $siteConfig->CanViewType = 'Anyone';
        $siteConfig->write();

        return parent::setUp();
    }

    public function testConstruct()
    {
        $factory = new DocumentFactory();
        $this->assertInstanceOf(FieldResolver::class, $factory->getFieldResolver());
    }

    public function testBuildItems()
    {
        /** @var Page $curPage */
        foreach (SiteTree::get() as $curPage) {
            $curPage->write();
            $curPage->publishRecursive();
        }
        $factory = new DocumentFactory();
        $index = new TestIndex();
        $fields = $index->getFieldsForIndexing();
        $client = new Client([]);
        $update = $client->createUpdate();
        /** @var BufferedAdd $buffer */
        $buffer = $client->getPlugin('bufferedadd');
        $factory->setClass(SiteTree::class);
        $factory->setItems(Page::get());
        $this->assertEquals(SiteTree::class, $factory->getClass());
        $factory->buildItems($fields, $index, $update, $buffer);
        $this->assertInstanceOf(BaseIndex::class, $factory->getFieldResolver()->getIndex());
    }

    public function testSanitiseField()
    {
        $this->assertEquals('hello', getShortFieldName('Test\\Name\\hello'));
    }

    public function testGetLogger()
    {
        $this->assertInstanceOf(LoggerInterface::class, (new DocumentFactory())->getLogger());
    }

    public function testDebug()
    {
        $factory = new DocumentFactory();
        $this->assertFalse($factory->isDebug());
        $this->assertTrue($factory->setDebug(true)->isDebug());
    }
}
