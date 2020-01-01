<?php
/**
 * class SolrLog|Firesphere\SolrSearch\Models\SolrLog Solr logging to be read from the CMS
 *
 * @package Firesphere\SolrSearch\Models
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Models;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \Firesphere\SolrSearch\Models\SolrError
 *
 * @package Firesphere\SolrSearch\Models
 * @property string $Timestamp
 * @property string $Index
 * @property string $Type
 * @property string $Level
 * @property string $Message
 */
class SolrLog extends DataObject implements PermissionProvider
{
    /**
     * Used to give the Gridfield rows a corresponding colour
     *
     * @var array
     */
    protected static $row_color = [
        'ERROR' => 'alert alert-danger',
        'WARN'  => 'alert alert-warning',
        'INFO'  => 'alert alert-info',
    ];
    /**
     * @var string Database table name
     */
    private static $table_name = 'SolrLog';
    /**
     * @var array Database columns
     */
    private static $db = [
        'Timestamp' => 'Datetime',
        'Index'     => 'Varchar(255)',
        'Type'      => 'Enum("Config,Index,Query")',
        'Level'     => 'Varchar(10)',
        'Message'   => 'Text',
    ];
    /**
     * @var array Core this log entry belongs too
     */
    private static $has_one = [
        'SolrCore' => SolrCore::class
    ];
    /**
     * @var array Summary fields
     */
    private static $summary_fields = [
        'Timestamp',
        'Index',
        'Type',
        'Level',
    ];
    /**
     * @var array Searchable fields
     */
    private static $searchable_fields = [
        'Created',
        'Timestamp',
        'Index',
        'Type',
        'Level',
    ];
    /**
     * @var array Timestamp is indexed
     */
    private static $indexes = [
        'Timestamp' => true,
    ];
    /**
     * @var string Default sort
     */
    private static $default_sort = 'Timestamp DESC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SolrCoreID', 'SolrCore']);

        return $fields;
    }

    /**
     * Convert the Timestamp to a DBDatetime for compatibility
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->Timestamp = DBDatetime::create()->setValue(strtotime($this->Timestamp));
    }

    /**
     * Return the first line of this log item error
     *
     * @return string
     */
    public function getLastErrorLine()
    {
        $lines = explode(PHP_EOL, $this->Message);

        return $lines[0];
    }

    /**
     * Not creatable by users
     *
     * @param null|Member $member
     * @param array $context
     * @return bool|mixed
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * Not editable by users
     *
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * Member has view access?
     *
     * @param null|Member $member
     * @return bool|mixed
     */
    public function canView($member = null)
    {
        return parent::canView($member);
    }

    /**
     * Only deleteable by admins or when in dev mode to clean up
     *
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

    /**
     * Get the extra classes to colour the gridfield rows
     *
     * @return mixed|string
     */
    public function getExtraClass()
    {
        $classMap = static::$row_color;

        return $classMap[$this->Level] ?? 'alert alert-info';
    }


    /**
     * Return a map of permission codes to add to the dropdown shown in the Security section of the CMS.
     * array(
     *   'VIEW_SITE' => 'View the site',
     * );
     *
     * @return array
     */
    public function providePermissions()
    {
        return [
            'DELETE_LOG' => [
                'name'     => _t(self::class . '.PERMISSION_DELETE_DESCRIPTION', 'Delete Solr logs'),
                'category' => _t('Permissions.LOGS_CATEGORIES', 'Solr logs permissions'),
                'help'     => _t(
                    self::class . '.PERMISSION_DELETE_HELP',
                    'Permission required to delete existing Solr logs.'
                ),
            ],
            'VIEW_LOG'   => [
                'name'     => _t(self::class . '.PERMISSION_VIEW_DESCRIPTION', 'View Solr logs'),
                'category' => _t('Permissions.LOGS_CATEGORIES', 'Solr logs permissions'),
                'help'     => _t(
                    self::class . '.PERMISSION_VIEW_HELP',
                    'Permission required to view existing Solr logs.'
                ),
            ],
        ];
    }
}
