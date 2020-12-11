<?php
/**
 * class GridFieldExtension|Firesphere\SolrSearch\Extensions\GridFieldExtension Add colours to the GridField
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Extensions;

use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * Class GridFieldExtension
 * Dirty hack to get the alert/warning/info classes in to the gridfield
 *
 * @package Firesphere\Solr\Search
 * @property GridField|GridFieldExtension $owner
 */
class GridFieldExtension extends Extension
{

    /**
     * Add the visibility classes to the GridField
     *
     * @param array $classes
     * @param int $total
     * @param string $index
     * @param DataObject $record
     */
    public function updateNewRowClasses(array &$classes, int $total, string $index, DataObject $record)
    {
        if ($record instanceof SolrLog) {
            $classes[] = $record->getExtraClass();
        }
    }
}
