<?php

namespace Firesphere\SolrSearch\Admins;

use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \Firesphere\SolrSearch\Admins\SearchAdmin
 *
 * @todo implement search administration, e.g. Elevation and Facets
 * @summary Manage or see the Solr configuration. Default implementation of SilverStripe ModelAdmin
 * Nothing to see here
 */
class SearchAdmin extends ModelAdmin
{
    private static $managed_models = [
        SolrLog::class
    ];

    private static $menu_icon_class = 'font-icon-search';

    private static $url_segment = 'searchadmin';

    private static $menu_title = 'Search';
}
/** **/
