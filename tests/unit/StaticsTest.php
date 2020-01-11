<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\Statics;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBBigInt;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBClassName;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBDouble;
use SilverStripe\ORM\FieldType\DBEnum;
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
    public static $expected = [
        'Enum'                                       => 'text',
        DBHTMLText::class                            => 'htmltext',
        'DBHTMLText'                                 => 'htmltext',
        'ClassName'                                  => 'text',
        'BigInt'                                     => 'tint',
        'DBDouble'                                   => 'tdouble',
        DBText::class                                => 'text',
        'DBBigInt'                                   => 'tint',
        DBForeignKey::class                          => 'tint',
        'DBText'                                     => 'text',
        DBDouble::class                              => 'tdouble',
        DBBoolean::class                             => 'boolean',
        DBBigInt::class                              => 'tint',
        'ForeignKey'                                 => 'tint',
        'DBDatetime'                                 => 'tdate',
        'DBVarchar'                                  => 'text',
        '*'                                          => 'text',
        'Varchar'                                    => 'text',
        'DBMoney'                                    => 'tfloat',
        'Boolean'                                    => 'boolean',
        'Date'                                       => 'tdate',
        'DBDate'                                     => 'tdate',
        'HTMLVarchar'                                => 'htmltext',
        'DBEnum'                                     => 'text',
        DBHTMLVarchar::class                         => 'htmltext',
        'Int'                                        => 'tint',
        'DBHTMLVarchar'                              => 'htmltext',
        DBInt::class                                 => 'tint',
        'Double'                                     => 'tdouble',
        'Decimal'                                    => 'tfloat',
        DBVarchar::class                             => 'text',
        DBCurrency::class                            => 'tfloat',
        'DBForeignKey'                               => 'tint',
        'DBCurrency'                                 => 'tfloat',
        DBFloat::class                               => 'tfloat',
        'DBInt'                                      => 'tint',
        DBClassName::class                           => 'text',
        'DBFloat'                                    => 'tfloat',
        'DBClassName'                                => 'text',
        'DBDecimal'                                  => 'tfloat',
        DBDecimal::class                             => 'tfloat',
        DBDatetime::class                            => 'tdate',
        'Text'                                       => 'text',
        'Float'                                      => 'tfloat',
        'Datetime'                                   => 'tdate',
        'Currency'                                   => 'tfloat',
        'HTMLText'                                   => 'htmltext',
        'DBBoolean'                                  => 'boolean',
        DBMoney::class                               => 'tfloat',
        'Money'                                      => 'tfloat',
        DBEnum::class                                => 'text',
        DBDate::class                                => 'tdate',
        'SilverStripe\\ORM\\FieldType\\HTMLFragment' => 'htmltext',
        'HTMLFragment'                               => 'htmltext',
        'PrimaryKey'                                 => 'tint',
    ];

    public function testGetTypesMap()
    {
        $this->assertEquals(static::$expected, Statics::getTypeMap());
    }
}
