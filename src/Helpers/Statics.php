<?php


namespace Firesphere\SolrSearch\Helpers;

use SilverStripe\Core\Config\Configurable;

/**
 * Class Statics
 * Typemap static helper
 * @package Firesphere\SolrSearch\Helpers
 */
class Statics
{
    use Configurable;

    /**
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
