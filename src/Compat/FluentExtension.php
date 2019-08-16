<?php

namespace Firesphere\SolrSearch\Compat;

use SilverStripe\Core\Extension;
use TractorCow\Fluent\Extension\FluentChangesExtension;

if (!class_exists('TractorCow\\Fluent\\Model\\Locale')) {
    return;
}

/**
 * Class FluentExtension
 * @package Firesphere\SolrSearch\Compat
 */
class FluentExtension extends Extension
{

    public function getModifiedData($foundData)
    {
        // @todo check modify the found data with the specifics for each language
        // Should return a set. Or maybe a simple array? If it's there, we can filter
    }
}
