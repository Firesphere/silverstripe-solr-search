<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\LoginAttempt;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Firesphere\SolrSearch\Compat\DataObjectExtension
 *
 * Extend every DataObject with the option to update the index.
 *
 * @package Firesphere\SolrSearch\Extensions
 * @property Page|CarouselItem|BlocksPage|Item|File|Image|SiteConfig|ChangeSetItem|SecurityAlert|Package|ElementalArea|ElementForm|Blog|SiteTree|Group|Member|EditableCustomRule|EditableFormField|UserDefinedForm|EditableOption|DataObjectExtension $owner
 */
class DataObjectExtension extends DataExtension
{
    /**
     * canView cache
     *
     * @var array
     */
    public static $canViewClasses = [];
    /**
     * Member cache
     *
     * @var DataList
     */
    protected static $members;
    /**
     * Don't check these classes
     *
     * @var array
     */
    protected static $excludedClasses = [
        DirtyClass::class,
        ChangeSet::class,
        ChangeSetItem::class,
        SolrLog::class,
        LoginAttempt::class,
        Member::class,
        SiteConfig::class,
    ];

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
        if (Controller::curr()->getRequest()->getURL() &&
            strpos('dev/build', Controller::curr()->getRequest()->getURL()) !== false
        ) {
            return;
        }
        if (!$owner->hasExtension(Versioned::class)) {
            $this->pushToSolr($owner);
        }
    }

    /**
     * Try to push the newly updated item to Solr
     *
     * @param DataObject $owner
     * @throws ValidationException
     * @throws GuzzleException
     * @throws ReflectionException
     */
    protected function pushToSolr(DataObject $owner): void
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
            Versioned::set_reading_mode(Versioned::DEFAULT_MODE);
            $service->setInDebugMode(false);
            $service->updateItems(ArrayList::create([$owner]), SolrCoreService::UPDATE_TYPE);
            // If we don't get an exception, mark the item as clean
            // Added bonus, array_flip removes duplicates
            $this->clearIDs($owner, $ids, $record);
            Versioned::set_reading_mode($mode);
        } catch (Exception $error) {
            Versioned::set_reading_mode($mode);
            $this->registerException($ids, $record, $error);
        }
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
        /** @var DataObject $owner */
        $owner = $this->owner;
        $this->pushToSolr($owner);
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
        parent::onAfterDelete();
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
        // Return empty if it's not allowed to show in search
        // The setting needs to be explicitly false, to avoid any possible collision
        // with objects not having the setting, thus being `null`
        /** @var DataObject|SiteTree $owner */
        $owner = $this->owner;
        // Return immediately if the owner has ShowInSearch not being `null`
        if ($owner->ShowInSearch === false || $owner->ShowInSearch === 0) {
            return [];
        }

        return self::$canViewClasses[$owner->ClassName] ?? $this->getMemberPermissions($owner);
    }

    /**
     * Get the view permissions for each member in the system
     *
     * @param DataObject|SiteTree $owner
     * @return array
     */
    protected function getMemberPermissions($owner): array
    {
        // Log out the current user to avoid collisions in permissions
        $currMember = Security::getCurrentUser();
        Security::setCurrentUser(null);

        if ($owner->canView(null)) {
            self::$canViewClasses[$owner->ClassName] = ['1-null'];

            // Anyone can view
            return ['1-null'];
        }
        // Return a default '0-0' to basically say "noboday can view"
        $return = ['0-0'];
        foreach (self::getMembers() as $member) {
            $return[] = sprintf('%s-%s', (int)$owner->canView($member), (int)$member->ID);
        }

        if (!$owner->hasField('ShowInSearch')) {
            self::$canViewClasses[$owner->ClassName] = $return;
        }

        Security::setCurrentUser($currMember);

        return $return;
    }

    /**
     * Get the static list of members
     *
     * @return DataList
     */
    protected static function getMembers()
    {
        if (empty(self::$members)) {
            self::$members = Member::get();
        }

        return self::$members;
    }
}
