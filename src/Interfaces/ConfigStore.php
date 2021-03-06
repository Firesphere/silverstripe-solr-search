<?php
/**
 * class ConfigStore|Firesphere\SolrSearch\Interfaces\ConfigStore Interface for managing the
 * store configuration of a core
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Interfaces;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface ConfigStore
 *
 * @package Firesphere\Solr\Search
 */
interface ConfigStore
{
    /**
     * Upload a file to Solr for index $index
     *
     * @param $index string - The name of an index (which is also used as the name of the Solr core for the index)
     * @param $file string - A path to a file to upload. The base name of the file will be used on the remote side
     * @return null|ResponseInterface
     */
    public function uploadFile($index, $file);

    /**
     * Upload a file to Solr from a string for index $index
     *
     * @param string $index - The name of an index (which is also used as the name of the Solr core for the index)
     * @param string $filename - The base name of the file to use on the remote side
     * @param string $string - The content to upload
     * @return null|ResponseInterface
     */
    public function uploadString($index, $filename, $string);

    /**
     * Get the instanceDir to tell Solr to use for index $index
     *
     * @param string|null $index - The name of an index (which is also used as the name of the Solr core for the index)
     * @return string
     */
    public function instanceDir($index);

    /**
     * Get the path of where the index configuration is stored
     *
     * @return string
     */
    public function getPath();
}
