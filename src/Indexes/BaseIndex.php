<?php


namespace Firesphere\SearchConfig\Indexes;

use Firesphere\SearchConfig\Queries\BaseQuery;
use Firesphere\SearchConfig\Services\SolrCoreService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\Debug;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore;
use SilverStripe\View\ViewableData;
use Solarium\Core\Client\Client;

abstract class BaseIndex extends ViewableData
{

    /**
     * @return string
     */
    abstract public function getIndexName();

    /**
     * @param BaseQuery $query
     */
    public function doSearch($query)
    {
        // @todo make filterQuerySelects instead of a single query
        $q = $this->buildSolrQuery($query);

        // Solarium
        $config = Config::inst()->get(SolrCoreService::class, 'config');
        $client = new Client($config);

        $solariumQuery = $client->createSelect([
            'query' => trim(implode(' ', $q)),
            'start' => $query->getStart(),
            'rows'  => $query->getRows(),
            'fields' => $query->getFields() ?: '*,score',
            'sort' => $query->getSort() ?: ''
        ]);

        $result = $client->select($solariumQuery);

        Debug::dump($result);
    }


    public function getExtrasPath()
    {
        // @todo configurable but with default to the current absolute path
        $dir = __DIR__;
        $dir = rtrim(substr($dir, 0, strpos($dir, 'searchconfig') + strlen('searchconfig')), '/');

        $confDirs = SolrCoreService::config()->get('paths');

        return sprintf($confDirs['extras'], $dir);
    }

    /**
     * Upload config for this index to the given store
     *
     * @param SolrConfigStore $store
     */
    public function uploadConfig($store, $id)
    {
        // @todo use types/schema/elevate rendering
//        // Upload the config files for this index
//        $store->uploadString(
//            $this->getIndexName(),
//            'schema.xml',
//            (string)$this->generateSchema()
//        );

        // Upload additional files
        foreach (glob($this->getExtrasPath() . '/*') as $file) {
            if (is_file($file)) {
                $store->uploadFile($this->getIndexName() . $id, $file);
            }
        }
    }

    /**
     * @param BaseQuery $query
     * @return array
     */
    protected function buildSolrQuery($query)
    {
        $q = [];
        $hlq = [];
        foreach ($query->getTerms() as $search) {
            $text = $search['text'];
            // Split the query on parts between double quotes and rest of the text, to keep quoted parts as one
            preg_match_all('/"[^"]*"|\S+/', $text, $parts);

            $fuzzy = $search['fuzzy'] ? '~' : '';

            // @todo simplify
            $fields = isset($search['fields']) ? $search['fields'] : [];
            if (isset($search['boost'])) {
                $fields = array_merge($fields, array_keys($search['boost']));
            }

            // @todo use Solarium filterQuery instead if there are multiple queries with different fields
            foreach ($parts[0] as $part) {
                if ($fields) {
                    $searchq = [];
                    foreach ($fields as $field) {
                        $boost = isset($search['boost'][$field]) ? '^' . $search['boost'][$field] : '';
                        // Escape namespace separators in class names
                        $field = str_replace('\\', '\\\\', $field);

                        $searchq[] = "{$field}:{$part}{$fuzzy}{$boost}";
                    }
                    $q[] = '+(' . implode(' OR ', $searchq) . ')';
                } else {
                    $q[] = ' ' . $part . $fuzzy;
                }
                $hlq[] = $part;
            }
        }

        return count($q) ? $q : ['*'];
    }
}
