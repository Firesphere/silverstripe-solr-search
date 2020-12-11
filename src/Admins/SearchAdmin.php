<?php
/**
 * class SearchAdmin|Firesphere\SolrSearch\Admins\SearchAdmin Base admin for Synonyms, logs and dirty classes
 *
 * @package Firesphere\SolrSearch
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Admins;

use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Models\SearchSynonym;
use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;

/**
 * Class \Firesphere\SolrSearch\Admins\SearchAdmin
 * Manage or see the Solr configuration. Default implementation of SilverStripe ModelAdmin
 * Nothing to see here
 *
 * @package Firesphere\SolrSearch
 */
class SearchAdmin extends ModelAdmin
{
    /**
     * @var array Models managed by this admin
     */
    private static $managed_models = [
        SearchSynonym::class,
        SolrLog::class,
        DirtyClass::class,
    ];

    /**
     * @var string Add a pretty magnifying glass to the sidebar menu
     */
    private static $menu_icon_class = 'font-icon-search';

    /**
     * @var string Where to find me
     */
    private static $url_segment = 'searchadmin';

    /**
     * @var string My name
     */
    private static $menu_title = 'Search';

    /**
     * Make sure the custom CSS for highlighting in the GridField is loaded
     */
    public function init()
    {
        parent::init();

        Requirements::css('firesphere/solr-search:client/dist/main.css');
    }
}
