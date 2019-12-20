<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\InheritedPermissionsExtension;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Firesphere\SolrSearch\Compat\DataObjectExtension
 *
 * Extend every DataObject with the option to update the index.
 *
 * @package Firesphere\SolrSearch\Extensions
 * @property DataObject|DataObjectExtension $owner
 */
class DataObjectExtension extends DataExtension
{
    /**
     * @var array Cached permission list
     */
    public static $cachedClasses;
    /**
     * @var SiteConfig Current siteconfig
     */
    protected static $siteConfig;
    /**
     * @var ArrayList|Member[] List of all the members in the system
     */
    protected static $memberList;

    /**
     * Push the item to solr if it is not versioned
     * Update the index after write.
     *
     * @throws ValidationException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function onAfterWrite()
    {
        /** @var DataObject $owner */
        $owner = $this->owner;

        if ($this->shouldPush() && !$owner->hasExtension(Versioned::class)) {
            $this->pushToSolr($owner);
        }
    }

    /**
     * Should this write be pushed to Solr
     * @return bool
     */
    protected function shouldPush()
    {
        return !(Controller::curr()->getRequest()->getURL() &&
            strpos('dev/build', Controller::curr()->getRequest()->getURL()) !== false);
    }

    /**
     * Try to push the newly updated item to Solr
     *
     * @param DataObject $owner
     * @throws ValidationException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    protected function pushToSolr(DataObject $owner)
    {
        $service = new SolrCoreService();
        if (!$service->isValidClass($owner->ClassName)) {
            return;
        }
        /** @var DataObject $owner */
        $record = $this->getDirtyClass($owner, SolrCoreService::UPDATE_TYPE);

        $ids = json_decode($record->IDs, 1) ?: [];
        $mode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode(Versioned::LIVE);
            $service->setInDebugMode(false);
            $type = SolrCoreService::UPDATE_TYPE;
            // If the object should not show in search, remove it
            if ($owner->ShowInSearch !== null && (bool)$owner->ShowInSearch === false) {
                $type = SolrCoreService::DELETE_TYPE;
            }
            $service->updateItems(ArrayList::create([$owner]), $type);
            // If we don't get an exception, mark the item as clean
            // Added bonus, array_flip removes duplicates
            $this->clearIDs($owner, $ids, $record);
        } catch (Exception $error) {
            Versioned::set_reading_mode($mode);
            $this->registerException($ids, $record, $error);
        }
        Versioned::set_reading_mode($mode);
    }

    /**
     * Find or create a new DirtyClass for recording dirty IDs
     *
     * @param DataObject $owner
     * @param string $type
     * @return DirtyClass
     * @throws ValidationException
     */
    protected function getDirtyClass(DataObject $owner, $type)
    {
        // Get the DirtyClass object for this item
        /** @var null|DirtyClass $record */
        $record = DirtyClass::get()->filter(['Class' => $owner->ClassName, 'Type' => $type])->first();
        if (!$record || !$record->exists()) {
            $record = DirtyClass::create([
                'Class' => $owner->ClassName,
                'Type'  => $type,
            ]);
            $record->write();
        }

        return $record;
    }

    /**
     * Remove the owner ID from the dirty ID set
     *
     * @param DataObject $owner
     * @param array $ids
     * @param DirtyClass $record
     * @throws ValidationException
     */
    protected function clearIDs(DataObject $owner, array $ids, DirtyClass $record): void
    {
        $values = array_flip($ids);
        unset($values[$owner->ID]);

        $record->IDs = json_encode(array_keys($values));
        $record->write();
    }

    /**
     * Register the exception of the attempted index for later clean-up use
     *
     * @param array $ids
     * @param $record
     * @param Exception $error
     * @throws ValidationException
     * @throws GuzzleException
     */
    protected function registerException(array $ids, $record, Exception $error): void
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        $ids[] = $owner->ID;
        // If we don't get an exception, mark the item as clean
        $record->IDs = json_encode($ids);
        $record->write();
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->warn(
            sprintf(
                'Unable to alter %s with ID %s',
                $owner->ClassName,
                $owner->ID
            )
        );
        $solrLogger = new SolrLogger();
        $solrLogger->saveSolrLog('Index');

        $logger->error($error->getMessage());
    }

    /**
     * Push the item to Solr after publishing
     *
     * @throws ValidationException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function onAfterPublish()
    {
        if ($this->shouldPush()) {
            /** @var DataObject $owner */
            $owner = $this->owner;
            $this->pushToSolr($owner);
        }
    }

    /**
     * Attempt to remove the item from Solr
     *
     * @throws ValidationException
     * @throws GuzzleException
     */
    public function onAfterDelete(): void
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        /** @var DirtyClass $record */
        $record = $this->getDirtyClass($owner, SolrCoreService::DELETE_TYPE);

        $ids = json_decode($record->IDs, 1) ?: [];

        try {
            (new SolrCoreService())->updateItems(ArrayList::create([$owner]), SolrCoreService::DELETE_TYPE);
            // If successful, remove it from the array
            // Added bonus, array_flip removes duplicates
            $this->clearIDs($owner, $ids, $record);
        } catch (Exception $error) {
            $this->registerException($ids, $record, $error);
        }
    }

    /**
     * Get the view status for each member in this object
     *
     * @return array
     */
    public function getViewStatus(): array
    {
        // return as early as possible
        /** @var DataObject|SiteTree $owner */
        $owner = $this->owner;
        if (isset(static::$cachedClasses[$owner->ClassName])) {
            return static::$cachedClasses[$owner->ClassName];
        }

        // Make sure the siteconfig is loaded
        if (!static::$siteConfig) {
            static::$siteConfig = SiteConfig::current_site_config();
        }
        // Return false if it's not allowed to show in search
        // The setting needs to be explicitly false, to avoid any possible collision
        // with objects not having the setting, thus being `null`
        // Return immediately if the owner has ShowInSearch not being `null`
        if ($owner->ShowInSearch === false || $owner->ShowInSearch === 0) {
            return ['false'];
        }

        $permissions = $this->getGroupViewPermissions($owner);

        if (!$owner->hasExtension(InheritedPermissionsExtension::class)) {
            static::$cachedClasses[$owner->ClassName] = $permissions;
        }

        return $permissions;
    }

    /**
     * Determine the view permissions based on group settings
     *
     * @param DataObject|SiteTree|SiteConfig $owner
     * @return array
     */
    protected function getGroupViewPermissions($owner): array
    {
        // Switches are not ideal, but it's a lot more readable this way!
        switch ($owner->CanViewType) {
            case 'LoggedInUsers':
                $return = ['false', 'LoggedIn'];
                break;
            case 'OnlyTheseUsers':
                $return = ['false'];
                $return = array_merge($return, $owner->ViewerGroups()->column('Code'));
                break;
            case 'Inherit':
                $parent = !$owner->ParentID ? static::$siteConfig : $owner->Parent();
                $return = $this->getGroupViewPermissions($parent);
                break;
            case 'Anyone': // View is either not implemented, or it's "Anyone"
                $return = ['null'];
                break;
            default:
                // Default to "Anyone can view"
                $return = ['null'];
        }

        return $return;
    }
}
