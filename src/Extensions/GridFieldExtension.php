<?php


namespace Firesphere\SolrSearch\Extensions;

use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

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
     * Add the visibility classes to the GridField
     * @param array $classes
     * @param int $total
     * @param int $index
     * @param DataObject $record
     */
    public function updateNewRowClasses(&$classes, $total, $index, $record)
    {
        if ($record instanceof SolrLog) {
            $classes[] = $record->getExtraClass();
        }
    }
}
