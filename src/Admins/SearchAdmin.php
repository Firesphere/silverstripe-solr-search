<?php

namespace Firesphere\SolrSearch\Admins;

use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;

/**
 * Class \Firesphere\SolrSearch\Admins\SearchAdmin
 *
 * @package Firesphere\SolrSearch\Admins
 *
 * Manage or see the Solr configuration. Default implementation of SilverStripe ModelAdmin
 * Nothing to see here
 */
class SearchAdmin extends ModelAdmin
{
    /**
     * Models managed by this admin
     * @var array
     */
    private static $managed_models = [
        SolrLog::class,
        DirtyClass::class
    ];

    /**
     * Add a pretty magnifying glass to the sidebar menu
     * @var string
     */
    private static $menu_icon_class = 'font-icon-search';

    /**
     * Where to find me
     * @var string
     */
    private static $url_segment = 'searchadmin';

    /**
     * My name
     * @var string
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
