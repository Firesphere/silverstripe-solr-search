<?php


namespace Firesphere\SolrSearch\Stores;

use Firesphere\SolrSearch\Interfaces\ConfigStore;
use SilverStripe\Control\Director;
use Solarium\Exception\RuntimeException;

/**
 * Class FileConfigStore
 * Store the config in a file storage on the local file system.
 * Store the config in a file storage on the local file system, usually in project/.solr/indexname
 *
 * @package Firesphere\SolrSearch\Stores
 */
class FileConfigStore implements ConfigStore
{

    /**
     * @var array Configuration to use
     */
    protected $config;

    /**
     * FileConfigStore constructor.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        if (empty($config)) {
            throw new RuntimeException('No valid config defined', 1);
        }
        // A relative folder should be rewritten to a writeable folder for the system
        if (Director::is_relative_url($config['path'])) {
            $config['path'] = Director::baseFolder() . '/' . $config['path'];
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
     * Get the target dir for the file saving
     *
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

    /**
     * Path to the store location
     *
     * @return mixed|string
     */
    public function getPath()
    {
        return $this->config['path'];
    }

    /**
     * Upload/load a file in to the storage
     *
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
     * Location of the instance
     *
     * @param string|null $index
     * @return string|null
     */
    public function instanceDir($index)
    {
        return $this->getPath() . '/' . $index;
    }

    /**
     * Get the config
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the config
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): FileConfigStore
    {
        $this->config = $config;

        return $this;
    }
}
