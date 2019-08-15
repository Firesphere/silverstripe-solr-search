<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Firesphere\SolrSearch\Compat\DataObjectExtension
 *
 * @property CarouselItem|BlocksPage|Item|File|Image|SiteConfig|ChangeSetItem|SecurityAlert|Package|ElementalArea|ElementForm|Blog|SiteTree|Group|Member|EditableCustomRule|EditableFormField|UserDefinedForm|EditableOption|DataObjectExtension $owner
 */
class DataObjectExtension extends DataExtension
{
    public const WRITE = 'write';
    public const DELETE = 'delete';
    /**
     * @var array
     */
    public static $canViewClasses = [];
    /**
     * @var DataList
     */
    protected static $members;
    /**
     * @var array
     */
    protected static $excludedClasses = [
        DirtyClass::class,
        ChangeSet::class,
        ChangeSetItem::class,
    ];

    /**
     * @throws ValidationException
     */
    public function onAfterWrite()
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        if (in_array($owner->ClassName, static::$excludedClasses, true) ||
            (Controller::curr()->getRequest()->getURL() &&
                strpos('dev/build', Controller::curr()->getRequest()->getURL()) !== false)
        ) {
            return;
        }
        if (!$owner->hasExtension(Versioned::class)) {
            $this->pushToSolr($owner);
        }
    }

    /**
     * @param DataObject $owner
     * @throws ValidationException
     */
    protected function pushToSolr(DataObject $owner): void
    {
        /** @var DataObject $owner */
        $record = $this->getDirtyClass($owner, self::WRITE);

        $ids = json_decode($record->IDs, 1) ?: [];
        $mode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode(Versioned::DEFAULT_MODE);
            $service = new SolrCoreService();
            $service->setInDebugMode(false);
            $service->updateItems(ArrayList::create([$owner]), SolrCoreService::UPDATE_TYPE);
            // If we don't get an exception, mark the item as clean
            // Added bonus, array_flip removes duplicates
            $values = array_flip($ids);
            unset($values[$owner->ID]);

            $record->IDs = json_encode(array_keys($values));
            $record->write();
            Versioned::set_reading_mode($mode);
        } catch (Exception $error) {
            Versioned::set_reading_mode($mode);
            $this->registerException($ids, $record, $error);
        }
    }

    /**
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
                'Type'  => $type
            ]);
            $record->write();
        }

        return $record;
    }

    /**
     * @param array $ids
     * @param $record
     * @param Exception $e
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
        $logger->error($error->getMessage());
    }

    /**
     * @throws ValidationException
     */
    public function onAfterPublish()
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        $this->pushToSolr($owner);
    }

    /**
     * @throws ValidationException
     */
    public function onAfterDelete(): void
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        /** @var DirtyClass $record */
        $record = $this->getDirtyClass($owner, self::DELETE);

        $ids = json_decode($record->IDs, 1) ?: [];
        parent::onAfterDelete();
        try {
            (new SolrCoreService())->updateItems(ArrayList::create([$owner]), SolrCoreService::DELETE_TYPE);
            // If successful, remove it from the array
            // Added bonus, array_flip removes duplicates
            $values = array_flip($ids);
            unset($values[$owner->ID]);

            $record->IDs = json_encode(array_keys($values));
            $record->write();
        } catch (Exception $error) {
            $this->registerException($ids, $record, $error);
        }
    }

    /**
     * Get the view status for each member in this object
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
     * @param DataObject|SiteTree $owner
     * @return array
     */
    protected function getMemberPermissions($owner): array
    {
        // Log out the current user to avoid collisions in permissions
        $currMember = Security::getCurrentUser();
        Security::setCurrentUser(null);

        $return = [];

        if ($owner->canView(null)) {
            $return[] = '1-null';
        } else {
            // Return a default '0-0' to basically say "noboday can view"
            $return[] = '0-0';
            foreach (self::getMembers() as $member) {
                $return[] = sprintf('%s-%s', (int)$owner->canView($member), (int)$member->ID);
            }
        }


        if (!$owner->hasField('ShowInSearch')) {
            self::$canViewClasses[$owner->ClassName] = $return;
        }

        Security::setCurrentUser($currMember);

        return $return;
    }

    /**
     * @return DataList
     */
    protected static function getMembers()
    {
        if (!self::$members) {
            self::$members = Member::get();
        }

        return self::$members
    }
}
