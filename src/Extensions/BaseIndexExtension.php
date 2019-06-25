<?php

namespace Firesphere\SolrSearch\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use Solarium\QueryType\Select\Result\Result;

/**
 * Class \Firesphere\SolrSearch\Extensions\BaseIndexExtension
 *
 * @property BaseIndexExtension $owner
 */
class BaseIndexExtension extends Extension
{

    /**
     * @param Result $results
     */
    public function onAfterSearch($results): void
    {
        if (Director::isDev() && Director::is_cli() && Controller::curr()->getRequest()->getVar('debugquery')) {
            /** @var \Solarium\Component\Result\Debug\Result $result */
            $result = $results->getDebug();
            Debug::message("Query string:\n" . $result->getQueryString());
            Debug::message("Parsed query:\n" . $result->getParsedQuery());
            Debug::message("Query parser:\n" . $result->getQueryParser());
            Debug::message('Explanation:');
            Debug::dump($result->getExplain());
        }
    }
}
