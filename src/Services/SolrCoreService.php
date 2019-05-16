<?php

namespace Firesphere\SearchConfig\Services;

use SilverStripe\Core\Config\Configurable;
use Solarium\Client;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;

class SolrCoreService
{
    use Configurable;

    /**
     * Create a new core
     * @param $core string - The name of the core
     * @param $instancedir string - The base path of the core on the server
     */
    public function coreCreate($core, $instancedir)
    {
        $config = static::config()->get('config');
        $client = new Client($config);

        /** @var Query $coreAdmin */
        $coreAdmin = $client->createCoreAdmin();

        $action = $coreAdmin->createCreate();

        $action->setCore($core);

        $action->setInstanceDir($instancedir);

        $coreAdmin->setAction($action);

        $client->coreAdmin($coreAdmin);
    }
}
