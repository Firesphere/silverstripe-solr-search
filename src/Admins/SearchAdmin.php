<?php

namespace Firesphere\SolrSearch\Admins;

use Firesphere\SolrSearch\Models\SearchClass;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \Firesphere\SolrSearch\Admins\SearchAdmin
 * @todo implement search administration, e.g. Elevation and Facets
 *
class SearchAdmin extends ModelAdmin
{
    private static $managed_models = [
        SearchClass::class
    ];

    private static $menu_icon_class = 'font-icon-search';

    private static $url_segment = 'searchadmin';

    private static $menu_title = 'Search';
}
/** **/
