<?php


namespace Firesphere\SolrSearch\Helpers;

use SilverStripe\Core\Config\Configurable;

class Statics
{

    use Configurable;

    /**
     * @todo move to YML
     * @var array map SilverStripe DB types to Solr types
     */
    protected static $typemap;

    /**
     * @return array
     */
    public static function getTypeMap()
    {
        return static::config()->get('typemap');
    }
}
