<?php


namespace Firesphere\SearchConfig\Helpers;

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

class Statics
{

    /**
     * @todo include DBTypes for casting
     * @var array map SilverStripe DB types to Solr types
     */
    protected static $typeMap = [
        '*'                  => 'text',
        'HTMLVarchar'        => 'htmltext',
        DBHTMLVarchar::class => 'htmltext',
        'Varchar'            => 'string',
        DBVarchar::class     => 'string',
        'Text'               => 'string',
        DBText::class        => 'string',
        'HTMLText'           => 'htmltext',
        DBHTMLText::class    => 'htmltext',
        'Boolean'            => 'boolean',
        DBBoolean::class     => 'boolean',
        'Date'               => 'tdate',
        DBDate::class        => 'tdate',
        'Datetime'           => 'tdate',
        DBDatetime::class    => 'tdate',
        'ForeignKey'         => 'tint',
        DBForeignKey::class  => 'tint',
        'Int'                => 'tint',
        DBInt::class         => 'tint',
        'Float'              => 'tfloat',
        DBFloat::class       => 'tfloat',
        'Double'             => 'tdouble',
        DBDouble::class      => 'tdouble',
        'Money'              => 'tfloat',
        DBMoney::class       => 'tfloat',
    ];

    /**
     * @return array
     */
    public static function getTypeMap()
    {
        return self::$typeMap;
    }
}
