<?php
/**
 * Class Firesphere\SolrSearch\Models\SolrCore For managing Solr cores in the cms
 *
 * @package Firesphere\SolrSearch\Models
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;

/**
 * Class SolrCore
 * @package Firesphere\SolrSearch\Models
 */
class SolrCore extends DataObject
{
    /**
     * @var string Table name for this class
     */
    private static $table_name = 'SolrCore';
    /**
     * @var string Singular name
     */
    private static $singular_name = 'Solr core';
    /**
     * @var string Plural name
     */
    private static $plural_name = 'Solr Cores';
    /**
     * @var array Database fields
     */
    private static $db = [
        'Title' => 'Varchar(255)'
    ];
    /**
     * @var array Synonyms and Logs related to this core
     */
    private static $has_many = [
        'SearchSynonyms' => SearchSynonym::class,
        'SolrLogs'       => SolrLog::class,
    ];
    /**
     * @var array Dirty classes this core has
     */
    private static $many_many = [
        'DirtyClasses' => DirtyClass::class
    ];

    /**
     * Update the CMS to make the Title uneditable
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->dataFieldByName('Title')->setReadonly(true)->setDisabled(true);

        return $fields;
    }

    /**
     * You can't create a Core DataObject manually. It requires a Solr core to exist.
     *
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = array())
    {
        return false;
    }
}
