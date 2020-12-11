<?php
/**
 * class Statics|Firesphere\SolrSearch\Helpers\Statics TypeMap support
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Helpers;

use SilverStripe\Core\Config\Configurable;

/**
 * Class Statics
 * Typemap static helper
 *
 * @package Firesphere\Solr\Search
 */
class Statics
{
    use Configurable;

    /**
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
