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
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;

/**
 * Class \Firesphere\SolrSearch\Compat\DataObjectExtension
 *
 * @property File|SiteConfig|SiteTree|Group|Member|DataObjectExtension $owner
 */
class DataObjectExtension extends DataExtension
{
    /**
     * @var DataList
     */
    protected static $members;
    protected static $excludedClasses = [
        DirtyClass::class,
        ChangeSet::class,
        ChangeSetItem::class,
    ];
    protected $canViewClasses = [];

    /**
     * @throws ValidationException
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (Controller::curr()->getRequest()->getURL() &&
            strpos('dev/build', Controller::curr()->getRequest()->getURL()) !== false
        ) {
            return;
        }
        /** @var DataObject $owner */
        $owner = $this->owner;
        if (!in_array($owner->ClassName, static::$excludedClasses, true)) {
            $record = $this->getDirtyClass($owner);

            $ids = json_decode($record->IDs, 1) ?: [];
            try {
                $service = new SolrCoreService();
                $service->setInDebugMode(false);
                $service->updateItems([$owner], SolrCoreService::UPDATE_TYPE);
                // If we don't get an exception, mark the item as clean
                $record->Clean = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);
                $record->IDs = json_encode($ids);
            } catch (Exception $e) {
                $this->registerException($ids, $record, $e);
            }
            $record->write();
        }
    }

    /**
     * @param DataObject $owner
     * @return DirtyClass
     * @throws ValidationException
     */
    protected function getDirtyClass(DataObject $owner)
    {
        // Get the DirtyClass object for this item
        /** @var null|DirtyClass $record */
        $record = DirtyClass::get()->filter(['Class' => $owner->ClassName])->first();
        if (!$record || !$record->exists()) {
            $record = DirtyClass::create([
                'Class' => $owner->ClassName,
                'Dirty' => DBDatetime::now()->Format(DBDatetime::ISO_DATETIME),
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
    protected function registerException(array $ids, $record, Exception $e): void
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        $ids[] = $owner->ID;
        // If we don't get an exception, mark the item as clean
        $record->Dirty = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);
        $record->IDs = json_encode($ids);
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->warn(
            sprintf(
                'Unable to alter %s with ID %s',
                $owner->ClassName,
                $owner->ID
            )
        );
        $logger->error($e->getMessage());
    }

    /**
     * @throws ValidationException
     */
    public function onAfterDelete(): void
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        /** @var DirtyClass $record */
        $record = $this->getDirtyClass($owner);

        $ids = json_decode($record->IDs, 1) ?: [];
        parent::onAfterDelete();
        try {
            (new SolrCoreService())->updateItems(ArrayList::create([$owner]), SolrCoreService::DELETE_TYPE);
            $record->Clean = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);
            $record->IDs = json_encode($ids);
        } catch (Exception $e) {
            $this->registerException($ids, $record, $e);
        }
        $record->write();
    }

    /**
     * Get the view status for each member in this object
     * @return array
     */
    public function getViewStatus(): array
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        $return = [];
        // Add null users if it's publicly viewable
        if ($owner->canView()) {
            return ['1-null'];
        }

        if (!self::$members) {
            self::$members = Member::get();
        }

        foreach (self::$members as $member) {
            $return[] = sprintf('%s-%s', $owner->canView($member), $member->ID);
        }

        return $return;
    }
}
