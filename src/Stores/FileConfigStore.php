<?php


namespace Firesphere\SolrSearch\Stores;

use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Solarium\Exception\RuntimeException;

/**
 * Class FileConfigStore
 * @package Firesphere\SolrSearch\Stores
 */
class FileConfigStore implements ConfigStore
{

    /**
     * @var array
     */
    protected $config;

    /**
     * FileConfigStore constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        if (empty($config) || !isset($config['path'])) {
            throw new RuntimeException('No valid config defined', 1);
        }

        $this->config = $config;
    }

    /**
     * Upload a file to the configuration store. Usually located in .solr/conf
     *
     * @param string $index
     * @param string $file
     * @return void|null
     */
    public function uploadFile($index, $file)
    {
        $targetDir = $this->getTargetDir($index);
        copy($file, $targetDir . '/' . basename($file));
    }

    /**
     * @param $index
     * @return string
     */
    public function getTargetDir($index)
    {
        $targetDir = "{$this->getPath()}/{$index}/conf";

        if (!is_dir($targetDir)) {
            $worked = @mkdir($targetDir, 0770, true);

            if (!$worked) {
                throw new RuntimeException(
                    sprintf('Failed creating target directory %s, please check permissions', $targetDir)
                );
            }
        }

        return $targetDir;
    }

    public function getPath()
    {
        return $this->config['path'];
    }

    /**
     * @param string $index
     * @param string $filename
     * @param string $string
     * @return void|null
     */
    public function uploadString($index, $filename, $string)
    {
        $targetDir = $this->getTargetDir($index);
        file_put_contents(sprintf('%s/%s', $targetDir, $filename), $string);
    }

    /**
     * @param string|null $index
     * @return string|null
     */
    public function instanceDir($index)
    {
        return $this->getPath() . '/' . $index;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): FileConfigStore
    {
        $this->config = $config;

        return $this;
    }
}
