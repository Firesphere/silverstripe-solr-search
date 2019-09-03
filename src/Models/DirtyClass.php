<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \Firesphere\SolrSearch\Models\DirtyClass
 * Keeping track of Dirty classes in Solr
 *
 * @package Firesphere\SolrSearch\Models
 * @property string $Type
 * @property string $Class
 * @property string $IDs
 */
class DirtyClass extends DataObject
{
    /**
     * @var string Table name
     */
    private static $table_name = 'DirtyClass';
    /**
     * @var array Database fields
     */
    private static $db = [
        'Type'  => 'Varchar(6)',
        'Class' => 'Varchar(512)',
        'IDs'   => 'Varchar(255)',
    ];
    /**
     * @var array Summary fields in CMS
     */
    private static $summary_fields = [
        'Class',
        'Type',
        'IDs'
    ];

    /**
     * Make the CMS fields readable
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Class', 'IDs']);

        $class = singleton($this->Class)->plural_name();

        $IDs = json_decode($this->IDs, true);

        $fields->addFieldsToTab('Root.Main', [
            ReadonlyField::create('Class', 'Class', $class),
            ReadonlyField::create('IDs', _t(self::class . '.DIRTYIDS', 'Dirty IDs'), $IDs)
        ]);

        return $fields;
    }

    /**
     * Nope, can't delete these
     * @param null|Member $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * Nope, can't edit these
     * @param null|Member $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * Nope, can't create these
     * @param null|Member $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }
}
