<?php


namespace Firesphere\SolrSearch\Helpers;

use Countable;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ValidationException;

/**
 * Class SolrLogger
 * @package Firesphere\SolrSearch\Helpers
 */
class SolrLogger
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * SolrLogger constructor.
     * @param null|Countable $handler
     */
    public function __construct($handler = null)
    {
        $config = SolrCoreService::config()->get('config');
        $hostConfig = array_shift($config['endpoint']);
        $guzzleConfig = [
            'base_uri' => $hostConfig['host'] . ':' . $hostConfig['port']
        ];
        if ($handler) {
            $guzzleConfig['handler'] = $handler;
        }

        $this->client = new Client($guzzleConfig);
    }

    /**
     * @param string $type
     * @param string $message
     * @param string $index
     * @throws GuzzleException
     * @throws ValidationException
     */
    public static function logMessage($type, $message, $index)
    {
        $solrLogger = new self();
        $solrLogger->saveSolrLog($type);
        /** @var SolrLog $lastError */
        $lastError = SolrLog::get()
            ->filter([
                'Index' => 'x:' . $index,
                'Level' => $type
            ])
            ->sort('Created ASC')
            ->first();

        $err = ($lastError === null) ? 'Unknown' : $lastError->getLastErrorLine();
        $message .= 'Last known error:' . PHP_EOL . $err;
        Debug::dump($message);
    }

    /**
     * @param string $type
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function saveSolrLog($type = 'Query'): void
    {
        $response = $this->client->request('GET', 'solr/admin/info/logging', [
            'query' => [
                'since' => 0,
                'wt'    => 'json'
            ]
        ]);

        $arrayResponse = json_decode($response->getBody(), true);

        foreach ($arrayResponse['history']['docs'] as $error) {
            $filter = [
                'Timestamp' => $error['time'],
                'Index'     => $error['core'] ?? 'x:Unknown',
                'Level'     => $error['level'],
            ];
            if (!SolrLog::get()->filter($filter)->exists()) {
                $logData = [
                    'Message' => $error['message'],
                    'Type'    => $type,
                ];
                $log = array_merge($filter, $logData);
                SolrLog::create($log)->write();
            }
        }
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return SolrLogger
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }
}
