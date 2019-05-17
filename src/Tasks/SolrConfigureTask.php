<?php


namespace Firesphere\SearchConfig\Tasks;


use Exception;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use ReflectionClass;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;

class SolrConfigureTask extends BuildTask
{

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     * @throws \ReflectionException
     */
    public function run($request)
    {
        parent::run($request);

        $this->extend('onBeforeSolrConfigureTask', $request);

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $store = $this->getSolrConfigStore();

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);
        foreach ($indexes as $instance) {
            $ref = new ReflectionClass($instance);
            if (!$ref->isInstantiable()) {
                continue;
            }
            $instance = singleton($instance);

            try {
                $this->updateIndex($instance, $store);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                $this
                    ->getLogger()
                    ->error('Failure: ' . $e->getMessage());
            }
        }

        if (isset($e)) {
            exit(1);
        }

        $this->extend('onAfterSolrConfigureTask', $request);
    }
}