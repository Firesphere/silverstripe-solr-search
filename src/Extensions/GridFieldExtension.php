<?php


namespace Firesphere\SolrSearch\Extensions;

use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;

/**
 * Class GridFieldExtension
 * Dirty hack to get the alert/warning/info classes in to the gridfield
 *
 * @package Firesphere\SolrSearch\Extensions
 * @property GridField|GridFieldExtension $owner
 */
class GridFieldExtension extends Extension
{

    /**
     * @param $classes
     * @param $total
     * @param $index
     * @param SolrLog $record
     */
    public function updateNewRowClasses(&$classes, $total, $index, $record)
    {
        if ($record instanceof SolrLog) {
            $classes[] = $record->getExtraClass();
        }
    }
}
