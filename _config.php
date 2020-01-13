<?php

/**
 * Get the short name of a field, e.g. App\Project\Page_Title will become Page_Title
 * Used to remove unwanted backslashes from Solr cores.
 *
 * Global method, to make it easier to address and less convoluted.
 *
 * @param string $name
 * @return string mixed
 */
function getShortFieldName($name)
{
    $name = explode('\\', $name);

    return end($name);
}
