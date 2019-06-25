<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Update\Query\Document\Document;

class DocumentFactoryTest extends SapphireTest
{
    protected static $expected_docs = [
        [
            '_documentid'      => 'Page-1',
            'ID'               => 1,
            'ClassName'        => 'Page',
            'ClassHierarchy'   =>
                [
                    'silverstripe\\view\\viewabledata'   => 'SilverStripe\\View\\ViewableData',
                    'silverstripe\\orm\\dataobject'      => 'SilverStripe\\ORM\\DataObject',
                    'silverstripe\\cms\\model\\sitetree' => 'SilverStripe\\CMS\\Model\\SiteTree',
                    'page'                               => 'Page',
                ],
            'ViewStatus'       =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'   => 'Home',
            'SiteTree_Content' => "<p>Welcome to SilverStripe! This is the default homepage. You can edit this page by opening <a href=\"admin/\">the CMS</a>.</p><p>You can now access the <a href=\"http://docs.silverstripe.org\">developer documentation</a>, or begin the <a href=\"http://www.silverstripe.org/learn/lessons\">SilverStripe lessons</a>.</p>",
        ],
        [
            '_documentid'      => 'Page-2',
            'ID'               => 2,
            'ClassName'        => 'Page',
            'ClassHierarchy'   =>
                [
                    'silverstripe\\view\\viewabledata'   => 'SilverStripe\\View\\ViewableData',
                    'silverstripe\\orm\\dataobject'      => 'SilverStripe\\ORM\\DataObject',
                    'silverstripe\\cms\\model\\sitetree' => 'SilverStripe\\CMS\\Model\\SiteTree',
                    'page'                               => 'Page',
                ],
            'ViewStatus'       =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'   => 'About Us',
            'SiteTree_Content' => "<p>You can fill this page out with your own content, or delete it and create your own pages.</p>",
        ],
        [
            '_documentid'      => 'Page-3',
            'ID'               => 3,
            'ClassName'        => 'Page',
            'ClassHierarchy'   =>
                [
                    'silverstripe\\view\\viewabledata'   => 'SilverStripe\\View\\ViewableData',
                    'silverstripe\\orm\\dataobject'      => 'SilverStripe\\ORM\\DataObject',
                    'silverstripe\\cms\\model\\sitetree' => 'SilverStripe\\CMS\\Model\\SiteTree',
                    'page'                               => 'Page',
                ],
            'ViewStatus'       =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'   => 'Contact Us',
            'SiteTree_Content' => "<p>You can fill this page out with your own content, or delete it and create your own pages.</p>",
        ],
        [
            '_documentid'      => 'SilverStripe\\ErrorPage\\ErrorPage-4',
            'ID'               => 4,
            'ClassName'        => 'SilverStripe\\ErrorPage\\ErrorPage',
            'ClassHierarchy'   =>
                [
                    'silverstripe\\view\\viewabledata'   => 'SilverStripe\\View\\ViewableData',
                    'silverstripe\\orm\\dataobject'      => 'SilverStripe\\ORM\\DataObject',
                    'silverstripe\\cms\\model\\sitetree' => 'SilverStripe\\CMS\\Model\\SiteTree',
                    'page'                               => 'Page',
                    'silverstripe\\errorpage\\errorpage' => 'SilverStripe\\ErrorPage\\ErrorPage',
                ],
            'ViewStatus'       =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'   => 'Page not found',
            'SiteTree_Content' => '<p>Sorry, it seems you were trying to access a page that doesn\'t exist.</p><p>Please check the spelling of the URL you were trying to access and try again.</p>',
        ],
        [
            '_documentid'      => 'SilverStripe\\ErrorPage\\ErrorPage-5',
            'ID'               => 5,
            'ClassName'        => 'SilverStripe\\ErrorPage\\ErrorPage',
            'ClassHierarchy'   =>
                [
                    'silverstripe\\view\\viewabledata'   => 'SilverStripe\\View\\ViewableData',
                    'silverstripe\\orm\\dataobject'      => 'SilverStripe\\ORM\\DataObject',
                    'silverstripe\\cms\\model\\sitetree' => 'SilverStripe\\CMS\\Model\\SiteTree',
                    'page'                               => 'Page',
                    'silverstripe\\errorpage\\errorpage' => 'SilverStripe\\ErrorPage\\ErrorPage',
                ],
            'ViewStatus'       =>
                [
                    0 => '1-null',
                ],
            'SiteTree_Title'   => 'Server error',
            'SiteTree_Content' => '<p>Sorry, there was a problem with handling your request.</p>',
        ],
    ];

    public function testConstruct()
    {
        $factory = new DocumentFactory();
        $this->assertInstanceOf(SearchIntrospection::class, $factory->getIntrospection());
    }

    public function testBuildItems()
    {
        $factory = new DocumentFactory();
        $index = new TestIndex();
        $fields = $index->getFieldsForIndexing();
        $client = new Client([]);
        $update = $client->createUpdate();
        $count = 0;
        $factory->setClass(SiteTree::class);
        $docs = $factory->buildItems($fields, $index, $update, 0, $count);

        $this->assertCount($count, $docs);
        $this->assertInternalType('array', $docs);
        $this->assertInstanceOf(BaseIndex::class, $factory->getIntrospection()->getIndex());
        /** @var Document $doc */
        foreach ($docs as $i => $doc) {
            // Debug::dump($doc->get
            $this->assertInstanceOf(Document::class, $doc);
            $fields = $doc->getFields();
            unset($fields['SiteTree_Created']); // Unset the Created, it changes per run
            $this->assertEquals(static::$expected_docs[$i], $fields);
        }
    }
}
