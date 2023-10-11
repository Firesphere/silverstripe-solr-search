<?php

namespace Firesphere\SolrSearch\Queries;

use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Interfaces\QueryBuilderInterface;
use Firesphere\SearchBackend\Queries\BaseQuery;

class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @param SolrQuery $query
     * @param CoreIndex $index
     * @return mixed
     */
    public static function buildQuery(BaseQuery $query, CoreIndex $index)
    {
        $clientQuery = $index->client->createSelect();
        $factory = $index->buildFactory($query, $clientQuery);

        $clientQuery = $factory->buildQuery();
        $index->setQueryTerms($factory->getQueryArray());

        $queryData = implode(' ', $index->getQueryTerms());
        $clientQuery->setQuery($queryData);

        return $clientQuery;
    }
}
