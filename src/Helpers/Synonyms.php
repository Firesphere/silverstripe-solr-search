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
     * Or any other synonyms defined if the user wishes to do so
     * @return string
     * @todo add ability to exclude the UK/US synonyms
     */
    public static function getSynonymsAsString($defaults = true)
    {
        $result = [];
        foreach (static::getSynonyms($defaults) as $synonym) {
            $result[] = implode(',', $synonym);
        }

        return implode(PHP_EOL, $result) . PHP_EOL;
    }

    public static function getSynonyms($defaults = true)
    {
        // If we want the defaults, load it in to the array, otherwise return an empty array
        $usuk = static::config()->get('usuk');
        $usuk = $defaults ? $usuk['synonyms'] : [];
        $synonyms = static::config()->get('synonyms') ?: [];

        return array_merge($usuk, $synonyms);
    }
}
