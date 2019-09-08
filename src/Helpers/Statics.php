<?php


namespace Firesphere\SolrSearch\Helpers;

use SilverStripe\Core\Config\Configurable;

/**
 * Class Statics
 * Typemap static helper
 *
 * @package Firesphere\SolrSearch\Helpers
 */
class Statics
{
    use Configurable;

    /**
     * The actual typemap should be read from config
     *
     * @var array map SilverStripe DB types to Solr types
     */
    protected static $typemap;

    /**
     * Get the typemap
     *
     * @return array
     */
    public static function getTypeMap()
    {
        return static::config()->get('typemap');
    }
}
