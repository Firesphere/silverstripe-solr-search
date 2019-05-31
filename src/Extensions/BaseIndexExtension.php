<?php

namespace Firesphere\SolrSearch\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataExtension;
use Solarium\Component\Result\Debug\Result as DebugResult;
use Solarium\QueryType\Select\Query\Query;

/**
 * Class BaseIndexExtension is an extension to help debug queries
 * If the variable is set, it will output the query debugging
 * @package Firesphere\SolrSearch\Extensions
 * \ */
class BaseIndexExtension extends DataExtension
{
    /**
     * Enable debugging
     * @param Query $query
     * @param $clientQuery
     */
    public function onBeforeSearch($query, $clientQuery)
    {
        $clientQuery->getDebug();
    }


    /**
     * Ignore the params, these are passed in.
     *
     * To enable debugging, add the following GET paramaters to your URL:
     * 'debugquery': Show the query string, parsed query and used parser
     * 'explain': Explain the results one by one, this could lead to a lot of output!
     * @param $nullify
     * @param $clientResult
     */
    public function onAfterSearch($nullify, $clientResult)
    {
        if (Director::isDev() && Controller::curr()->getRequest()->getVar('debugquery')) {
            /** @var DebugResult $result */
            $result = $clientResult->getDebug();
            Debug::message("Query string:\n" . $result->getQueryString());
            Debug::message("Parsed query:\n" . $result->getParsedQuery());
            Debug::message("Query parser:\n" . $result->getQueryParser());
            Debug::message("Explanation:\n" . $result->getExplain());
            $result = $clientResult->getDebug();
            var_dump("\nQuery string:\n" . $result->getQueryString());
            var_dump("\nParsed query:\n" . $result->getParsedQuery());
            var_dump("\nQuery parser:\n" . $result->getQueryParser());
            if (Controller::curr()->getRequest()->getVar('explain')) {
                foreach ($result->getExplain() as $key => $explanation) {
                    echo '<hr />';
                    var_dump('Document key: ' . $key);
                    var_dump('Value: ' . $explanation->getValue());
                    var_dump('Match: ' . $explanation->getMatch());
                    var_dump('Description: ' . $explanation->getDescription());
                    foreach ($explanation as $detail) {
                        var_dump('Value: ' . $detail->getValue());
                        var_dump('Match: ' . $detail->getMatch());
                        var_dump('Description: ' . $detail->getDescription());
                    }
                }
            }
        }
    }
}
