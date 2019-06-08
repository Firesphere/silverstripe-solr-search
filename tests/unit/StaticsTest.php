<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\Statics;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBDouble;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBForeignKey;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;

class StaticsTest extends SapphireTest
{
    public function testGetTypesMap()
    {
        $expected = [
            '*'                  => 'text',
            'HTMLVarchar'        => 'htmltext',
            DBHTMLVarchar::class => 'htmltext',
            'DBHTMLVarchar'      => 'htmltext',
            'Varchar'            => 'string',
            DBVarchar::class     => 'string',
            'DBVarchar'          => 'string',
            'Text'               => 'string',
            DBText::class        => 'string',
            'DBText'             => 'string',
            'HTMLText'           => 'htmltext',
            DBHTMLText::class    => 'htmltext',
            'DBHTMLText'         => 'htmltext',
            'Boolean'            => 'boolean',
            DBBoolean::class     => 'boolean',
            'DBBoolean'          => 'boolean',
            'Date'               => 'tdate',
            DBDate::class        => 'tdate',
            'DBDate'             => 'tdate',
            'Datetime'           => 'tdate',
            DBDatetime::class    => 'tdate',
            'DBDatetime'         => 'tdate',
            'ForeignKey'         => 'tint',
            DBForeignKey::class  => 'tint',
            'DBForeignKey'       => 'tint',
            'Int'                => 'tint',
            DBInt::class         => 'tint',
            'DBInt'              => 'tint',
            'Float'              => 'tfloat',
            DBFloat::class       => 'tfloat',
            'DBFloat'            => 'tfloat',
            'Double'             => 'tdouble',
            DBDouble::class      => 'tdouble',
            'DBDouble'           => 'tdouble',
            'Money'              => 'tfloat',
            DBMoney::class       => 'tfloat',
            'DBMoney'            => 'tfloat',
        ];

        $this->assertEquals($expected, Statics::getTypeMap());
    }
}
