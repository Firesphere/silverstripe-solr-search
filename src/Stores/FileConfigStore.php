<?php


namespace Firesphere\SolrSearch\Stores;

use Firesphere\SolrSearch\Interfaces\ConfigStore;
use RuntimeException;
use SilverStripe\Control\Director;

class FileConfigStore implements ConfigStore
{

    /**
     * @var string
     */
    protected $path;

    public function __construct($config = null)
    {
        if (!$config) {
            $path = Director::baseFolder() . '/.solr';
        } else {
            $path = $config['path'];
        }

        $this->path = $path;
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

    public function getTargetDir($index)
    {
        $targetDir = "{$this->path}/{$index}/conf";

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

    public function uploadString($index, $filename, $string)
    {
        $targetDir = $this->getTargetDir($index);
        file_put_contents("$targetDir/$filename", $string);
    }

    public function instanceDir($index)
    {
        return $this->path . '/' . $index;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return FileConfigStore
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }
}
