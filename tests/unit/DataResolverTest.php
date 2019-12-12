<?php

namespace Firesphere\SolrSearch\Tests;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\DataResolver;
use LogicException;
use Page;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DB;
use SilverStripe\View\ArrayData;
use stdClass;

class DataResolverTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    public static function ___setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        // This is a hack on my local dev to make fixtures populate temporary database
        $conn = DB::get_conn();
        $dbName = self::tempDB()->build();
        $conn->selectDatabase($dbName);
        $dbAdmin = new DatabaseAdmin();
        $dbAdmin->setRequest(HTTPRequestBuilder::createFromEnvironment());
        $dbAdmin->doBuild(true, true, true);
    }

    public function setUp()
    {
        parent::setUp();
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Class: stdClass is not supported.
     */
    public function testUnsupportedObjectException()
    {
        $obj = new stdClass();
        $obj->Created = '2019-07-04 22:01:00';
        $obj->Title = 'Hello generic class';
        $this->assertEqualsDump($obj->Title, DataResolver::identify($obj, 'Title'));
    }

    public function assertEqualsDump()
    {
        Debug::dump(func_get_args());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot identify, "UnknownColumn" from class "TestPage"
     */
    public function testCannotIdentifyExceptionForDataObject()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        DataResolver::identify($pageOne, 'UnknownColumn');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot identify, "UnknownColumn" from class "ArrayData"
     */
    public function testCannotIdentifyExceptionForArrayData()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        $arrayOne = new ArrayData($pageOne->toMap());
        DataResolver::identify($arrayOne, 'UnknownColumn');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Method, "SuperNice" not found on "DBDatetime"
     */
    public function testMethodNotFoundFromDBField()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        DataResolver::identify($pageOne, 'Created.SuperNice');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot identify, "Timestamp" from class "DBDatetime"
     */
    public function testCannotIdentifyExceptionForDBField()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        DataResolver::identify($pageOne, 'Created.Nice.Timestamp');
    }

    public function testDataObjectEmptyColumnIsToMap()
    {
        $objectOne = $this->objFromFixture(TestObject::class, 'objectOne');
        $this->assertEquals($objectOne->toMap(), DataResolver::identify($objectOne));
        $arrayOne = new ArrayData($objectOne->toMap());
        $this->assertEquals($objectOne->toMap(), $arrayOne->toMap());
        $this->assertEquals($arrayOne->toMap(), DataResolver::identify($arrayOne));
    }

    public function testDataListEmptyColumnIsToNestedArray()
    {
        $objectOne = $this->objFromFixture(TestObject::class, 'objectOne');
        $dataList = $objectOne->TestPages();
        $this->assertEquals($dataList->toNestedArray(), DataResolver::identify($dataList));
    }

    public function testDataObjectGetMethod()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        $this->assertEquals($pageOne->getSalutation(), DataResolver::identify($pageOne, 'Salutation'));
    }

    public function testDataObjectAttributes()
    {
        $mockDate = '2019-07-04 22:01:00';
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        $pageOne->Created = $mockDate;
        $pageOne->write();
        $this->assertEquals($pageOne->Title, DataResolver::identify($pageOne, 'Title'));
        $this->assertEquals($mockDate, $pageOne->Created);
        $this->assertEquals($mockDate, DataResolver::identify($pageOne, 'Created'));
        $this->assertEquals('Jul 4, 2019, 10:01 PM', DataResolver::identify($pageOne, 'Created.Nice'));
        $this->assertEquals('2019-07-04T22:01:00+00:00', DataResolver::identify($pageOne, 'Created.Rfc3339'));
        $this->assertEquals('1562277660', DataResolver::identify($pageOne, 'Created.Timestamp'));
        $this->assertEquals('1562277660', DataResolver::identify($pageOne, 'Created.getTimestamp'));
        $this->assertEquals('y-MM-dd HH:mm:ss', DataResolver::identify($pageOne, 'Created.ISOFormat'));
    }

    public function testDataArrayAttributes()
    {
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        $arrayOne = new ArrayData($pageOne->toMap());
        $this->assertEquals($arrayOne->Title, DataResolver::identify($arrayOne, 'Title'));
    }

    public function testDataTraversal()
    {
        $mockDate = '2019-07-04 22:01:00';
        $objectOne = $this->objFromFixture(TestObject::class, 'objectOne');
        $pageOne = $this->objFromFixture(TestPage::class, 'pageOne');
        $relationOne = $this->objFromFixture(TestRelationObject::class, 'relationOne');
        $relationOne->Created = $mockDate;
        $relationOne->write();
        $this->assertEquals($objectOne->toMap(), DataResolver::identify($pageOne, 'TestObject'));
        $this->assertEquals($objectOne->Title, DataResolver::identify($pageOne, 'TestObject.Title'));
        $this->assertEquals(0, DataResolver::identify($pageOne, 'TestObject.ShowInSearch'));
        $this->assertEquals('No', DataResolver::identify($pageOne, 'TestObject.ShowInSearch.Nice'));
        $this->assertEquals(
            $objectOne->TestRelation()->toNestedArray(),
            DataResolver::identify($pageOne, 'TestObject.TestRelation')
        );
        $this->assertEquals(
            $objectOne->TestRelation()->First()->toMap(),
            DataResolver::identify($pageOne, 'TestObject.TestRelation.First')
        );
        $this->assertEquals(
            $relationOne->Title,
            DataResolver::identify($pageOne, 'TestObject.TestRelation.First.Title')
        );
        $this->assertEquals($mockDate, DataResolver::identify($pageOne, 'TestObject.TestRelation.First.Created'));
        $this->assertEquals($mockDate, $relationOne->Created);
        $this->assertEquals(
            'Jul 4, 2019, 10:01 PM',
            DataResolver::identify($pageOne, 'TestObject.TestRelation.First.Created.Nice')
        );
        $this->assertEquals(
            '2019-07-04T22:01:00+00:00',
            DataResolver::identify($pageOne, 'TestObject.TestRelation.First.Created.Rfc3339')
        );
        $relationTwo = $this->objFromFixture(TestRelationObject::class, 'relationTwo');
        $relationTwo->Created = '2019-07-05 23:05:00';
        $relationTwo->write();
        $this->assertEquals(
            ['Jul 4, 2019, 10:01 PM', 'Jul 5, 2019, 11:05 PM'],
            DataResolver::identify($pageOne, 'TestObject.TestRelation.Created.Nice')
        );
    }

    public function testGetMethodReturnsArray()
    {
        /** @var TestRelationObject $relationOne */
        $relationOne = $this->objFromFixture(TestRelationObject::class, 'relationOne');
        $this->assertEquals($relationOne->getFarmAnimals(), DataResolver::identify($relationOne, 'FarmAnimals'));
    }

    public function testMethodReturnsString()
    {
        /** @var TestRelationObject $relationOne */
        $relationOne = $this->objFromFixture(TestRelationObject::class, 'relationOne');
        $this->assertEquals('cow', DataResolver::identify($relationOne, 'Cow'));
        $this->assertEquals('sheep', DataResolver::identify($relationOne, 'Sheep'));
        $this->assertEquals(['cow', 'sheep'], DataResolver::identify($relationOne, 'getFarmAnimals'));
        $this->assertEquals('cow', DataResolver::identify($relationOne, 'getCow'));
        $this->assertEquals('sheep', DataResolver::identify($relationOne, 'getSheep'));
    }

    public function testArrayList()
    {
        $list = new ArrayList(
            [
                new ArrayData(['Title' => 'one']),
                new ArrayData(['Title' => 'two']),
                new ArrayData(['Title' => 'three']),
            ]
        );
        $this->assertEquals($list->toNestedArray(), DataResolver::identify($list));
        $this->assertEquals($list->first()->Title, DataResolver::identify($list, 'First.Title'));
        $this->assertEquals($list->last()->Title, DataResolver::identify($list, 'Last.Title'));
    }
}
