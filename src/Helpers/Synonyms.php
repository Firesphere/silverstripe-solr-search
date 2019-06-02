<?php


namespace Firesphere\SolrSearch\Helpers;

use SilverStripe\Core\Config\Configurable;

/**
 * Class Synonyms
 * Source: @link https://raw.githubusercontent.com/heiswayi/spelling-uk-vs-us/
 * @package Firesphere\SolrSearch\Helpers
 */
class Synonyms
{
    use Configurable;

    protected static $synonyms;

    /**
     * Make the UK to US spelling synonyms as a newline separated string
     * @return string
     */
    public static function getSynonymsAsString()
    {
        $result = '';
        foreach (static::config()->get('synonyms') as $synonym) {
            $result .= implode(',', $synonym) . "\n";
        }

        return $result;
    }
}
