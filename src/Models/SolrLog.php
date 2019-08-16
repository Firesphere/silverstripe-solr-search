<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \Firesphere\SolrSearch\Models\SolrError
 *
 * @property string $Timestamp
 * @property string $Message
 * @property string $Index
 * @property string $Type
 * @property string $Level
 */
class SolrLog extends DataObject
{
    private static $table_name = 'SolrLog';

    private static $db = [
        'Timestamp' => 'Varchar(50)',
        'Message'   => 'Text',
        'Index'     => 'Varchar(255)',
        'Type'      => 'Enum("Config,Index,Query")',
        'Level'     => 'Varchar(10)'
    ];

    private static $summary_fields = [
        'Timestamp',
        'Index',
        'Type',
        'Level'
    ];

    private static $searchable_fields = [
        'Created',
        'Index',
        'Type',
        'Level'
    ];

    private static $indexes = [
        'Timestamp' => true,
    ];

    private static $sort = 'Created DESC';

    /**
     * @return mixed
     */
    public function getLastErrorLine()
    {
        $lines = explode("\n", $this->Message);

        return $lines[0];
    }

    /**
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canView($member = null)
    {
        return parent::canView($member);
    }

    /**
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canDelete($member = null)
    {
        if ($member) {
            return $member->inGroup('administrators') || Director::isDev();
        }

        return parent::canDelete($member) || Director::isDev();
    }
}
