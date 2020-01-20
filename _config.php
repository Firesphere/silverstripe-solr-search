<?php
/**
 * Basic SilverStripe config support file
 */

/**
 * Global method, to make it easier to address and less convoluted.
 *
 * Get the short name of a field, e.g. App\Project\Page_Title will become Page_Title
 * Used to remove unwanted backslashes from Solr cores.
 *
 * @param string $name Name to be shortened
 * @return string Short name
 * @package \
 */
function getShortFieldName($name)
{
    $name = explode('\\', $name);

    return end($name);
}
