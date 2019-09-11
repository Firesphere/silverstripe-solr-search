<?php


namespace Firesphere\SolrSearch\Traits;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use GuzzleHttp\Exception\GuzzleException;
use Solarium\Client;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;
use Solarium\QueryType\Server\CoreAdmin\Result\StatusResult;

/**
 * Trait CoreAdminTrait is the trait that helps with Admin operations.
 * Core operations, such as reload, load, create, unload etc. are supplied by this trait.
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait CoreAdminTrait
{
    /**
     * @var Query A core admin object
     */
    protected $admin;
    /**
     * @var Client The current client
     */
    protected $client;

    /**
     * Create a new core
     *
     * @param $core string - The name of the core
     * @param ConfigStore $configStore
     * @return bool
     * @throws Exception
     * @throws GuzzleException
     */
    public function coreCreate($core, $configStore): bool
    {
        $action = $this->admin->createCreate();

        $action->setCore($core);
        $action->setInstanceDir($configStore->instanceDir($core));
        $this->admin->setAction($action);
        try {
            $response = $this->client->coreAdmin($this->admin);

            return $response->getWasSuccessful();
        } catch (Exception $e) {
            $solrLogger = new SolrLogger();
            $solrLogger->saveSolrLog('Config');

            throw new Exception($e);
        }
    }

    /**
     * Reload the given core
     *
     * @param $core
     * @return StatusResult|null
     */
    public function coreReload($core)
    {
        $reload = $this->admin->createReload();
        $reload->setCore($core);

        $this->admin->setAction($reload);

        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }

    /**
     * Check the status of a core
     *
     * @deprecated backward compatibility stub
     * @param string $core
     * @return StatusResult|null
     */
    public function coreIsActive($core)
    {
        return $this->coreStatus($core);
    }

    /**
     * Get the core status
     *
     * @param string $core
     * @return StatusResult|null
     */
    public function coreStatus($core)
    {
        $status = $this->admin->createStatus();
        $status->setCore($core);

        $this->admin->setAction($status);
        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }

    /**
     * Remove a core from Solr
     *
     * @param string $core core name
     * @return StatusResult|null A result is successful
     */
    public function coreUnload($core)
    {
        $unload = $this->admin->createUnload();
        $unload->setCore($core);

        $this->admin->setAction($unload);
        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }
}
