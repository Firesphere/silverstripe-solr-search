<?php


namespace Firesphere\SolrSearch\Helpers;

use Countable;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;

/**
 * Class SolrLogger
 *
 * Log information from Solr to the CMS for reference
 *
 * @todo implement {@link LoggerInterface}
 * @package Firesphere\SolrSearch\Helpers
 */
class SolrLogger
{
    /**
     * Guzzle base client to communicate with Solr
     *
     * @var Client
     */
    protected $client;

    /**
     * SolrLogger constructor.
     *
     * @param null|Countable $handler
     */
    public function __construct($handler = null)
    {
        $config = SolrCoreService::config()->get('config');
        $hostConfig = array_shift($config['endpoint']);
        $guzzleConfig = [
            'base_uri' => $hostConfig['host'] . ':' . $hostConfig['port'],
        ];
        if ($handler) {
            $guzzleConfig['handler'] = $handler;
        }

        $this->client = new Client($guzzleConfig);
    }

    /**
     * Log the given message and dump it out.
     * Also boot the Log to get the latest errors from Solr
     *
     * @param string $type
     * @param string $message
     * @param string $index
     * @throws GuzzleException
     * @throws ValidationException
     */
    public static function logMessage($type, $message, $index): void
    {
        $solrLogger = new self();
        $solrLogger->saveSolrLog($type);
        /** @var SolrLog $lastError */
        $lastError = SolrLog::get()
            ->filter([
                'Index' => 'x:' . $index,
                'Level' => $type,
            ])
            ->sort('Timestamp DESC')
            ->first();

        $err = ($lastError === null) ? 'Unknown' : $lastError->getLastErrorLine();
        $message .= 'Last known error:' . PHP_EOL . $err;
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->alert($message);
        if ($type === 'ERROR' && (Director::is_cli() || Controller::curr()->getRequest()->getVar('unittest'))) {
            Debug::dump($message);
        }
    }

    /**
     * Save the latest Solr errors to the log
     *
     * @param string $type
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function saveSolrLog($type = 'Query'): void
    {
        $response = $this->client->request('GET', 'solr/admin/info/logging', [
            'query' => [
                'since' => 0,
                'wt'    => 'json',
            ],
        ]);

        $arrayResponse = json_decode($response->getBody(), true);

        foreach ($arrayResponse['history']['docs'] as $error) {
            $filter = [
                'Timestamp' => $error['time'],
                'Index'     => $error['core'] ?? 'x:Unknown',
                'Level'     => $error['level'],
            ];
            $this->findOrCreateLog($type, $filter, $error);
        }
    }

    /**
     * Attempt to find, otherwise create, a log object
     *
     * @param $type
     * @param array $filter
     * @param $error
     * @throws ValidationException
     */
    private function findOrCreateLog($type, array $filter, $error): void
    {
        // Not covered in tests. It's only here to make sure the connection isn't closed by a child process
        $conn = DB::is_active();
        if (!$conn) {
            $config = DB::getConfig();
            DB::connect($config);
        }
        if (!SolrLog::get()->filter($filter)->exists()) {
            $logData = [
                'Message' => $error['message'],
                'Type'    => $type,
            ];
            $log = array_merge($filter, $logData);
            SolrLog::create($log)->write();
            if (Director::is_cli() || Controller::curr()->getRequest()->getVar('unittest')) {
                /** @var LoggerInterface $logger */
                $logger = Injector::inst()->get(LoggerInterface::class);
                $logger->error($error['message']);
            }
        }
    }

    /**
     * Return the Guzzle Client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set the Guzzle client
     *
     * @param Client $client
     * @return SolrLogger
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }
}
