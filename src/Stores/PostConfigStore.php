<?php


namespace Firesphere\SolrSearch\Stores;

use Firesphere\SolrSearch\Interfaces\ConfigStore;
use GuzzleHttp\Client;
use Solarium\Exception\RuntimeException;

class PostConfigStore implements ConfigStore
{
    /**
     * @var array
     */
    protected $config;

    protected static $extensions = [
        'xml' => 'text/xml',
        'txt' => 'text/plain',
    ];

    /**
     * FileConfigStore constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        if (empty($config)) {
            throw new RuntimeException('No config defined', 1);
        }
        if (!isset($config['path'])) {
            $config['path'] = '/';
        }
        if (substr($config['path'], -1) !== '/') {
            $config['path'] .= '/';
        }

        $this->config = $config;
    }

    /**
     * Upload a file to Solr for index $index
     * @param $index string - The name of an index (which is also used as the name of the Solr core for the index)
     * @param $file string - A path to a file to upload. The base name of the file will be used on the remote side
     * @return null
     */
    public function uploadFile($index, $file)
    {
        $this->uploadString($index, $file, file_get_contents($file));
    }

    /**
     * Upload a file to Solr from a string for index $index
     * @param string $index - The name of an index (which is also used as the name of the Solr core for the index)
     * @param string $filename - The base name of the file to use on the remote side
     * @param string $string - The content to upload
     * @return null
     */
    public function uploadString($index, $filename, $string)
    {
        $info = pathinfo($filename);
        $clientConfig = [
            'base_uri' => $this->config['uri'],
            'headers'  => [
                'Content-Type' => static::$extensions[$info['extension']]
            ]
        ];
        // Add auth to the post if needed
        if (isset($this->config['auth'])) {
            $clientConfig['auth'] = [
                $this->config['auth']['username'],
                $this->config['auth']['password'],
            ];
        }

        $client = new Client($clientConfig);

        $path = sprintf('%sconfig/%s/%s', $this->getPath(), $index, $filename);

        $client->post($path, ['body' => $string]);
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->config['path'];
    }

    /**
     * Get the instanceDir to tell Solr to use for index $index
     * @param string|null $index string - The name of an index (which is also used as the name of the Solr core for the index)
     * @return null
     */
    public function instanceDir($index)
    {
        return $index;
    }
}
