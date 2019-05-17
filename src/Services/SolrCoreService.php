<?php

namespace Firesphere\SearchConfig\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\Deprecation;
use Solarium\Client;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;
use Solarium\QueryType\Server\CoreAdmin\Result\StatusResult;

class SolrCoreService
{
    use Configurable;

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
        $config = static::config()->get('config');
        $client = new Client($config);
    }

    /**
     * Create a new core
     * @param $core string - The name of the core
     * @param $instancedir string - The base path of the core on the server
     * @return StatusResult|null
     */
    public function coreCreate($core, $instancedir)
    {

        /** @var Query $coreAdmin */
        $coreAdmin = $this->client->createCoreAdmin();

        $action = $coreAdmin->createCreate();

        $action->setCore($core);

        $action->setInstanceDir($instancedir);

        $coreAdmin->setAction($action);

        $response = $this->client->coreAdmin($coreAdmin);

        return $response->getStatusResult();
    }

    /**
     * @param $core
     * @return StatusResult|null
     */
    public function coreReload($core)
    {
        $coreAdmin = $this->client->createCoreAdmin();
        $reload = $coreAdmin->createReload();
        $reload->setCore($core);

        $response = $this->client->coreAdmin($coreAdmin);

        return $response->getStatusResult();
    }

    /**
     * @param $core
     * @return StatusResult|null
     * @deprecated backward compatibility stub
     */
    public function coreIsActive($core)
    {
        Deprecation::notice('2.0', 'Use SolrCoreService::coreStatus($core) instead');

        return $this->coreStatus($core);
    }

    /**
     * @param $core
     * @return StatusResult|null
     */
    public function coreStatus($core)
    {
        $admin = $this->client->createCoreAdmin();
        $status = $admin->createStatus();
        $status->setCore($core);

        $response = $this->client->coreAdmin($admin);

        return $response->getStatusResult();
    }
}
