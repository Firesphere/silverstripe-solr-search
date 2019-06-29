<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrUpdate;
use Firesphere\SolrSearch\Models\DirtyClass;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
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
 * @property File|SiteConfig|SiteTree|Group|Member|DataObject|DataObjectExtension $owner
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
        SiteConfig::class,
        SiteTree::class
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
            return null;
        }
        if (!in_array($this->owner->ClassName, static::$excludedClasses, true)) {
            // Mark the current class as dirty
            /** @var DirtyClass $record */
            $record = DirtyClass::get()->filter(['Class' => $this->owner->ClassName])->first();
            if (!$record || !$record->exists()) {
                $record = DirtyClass::create([
                    'Class' => $this->owner->ClassName,
                    'Dirty' => DBDatetime::now()->Format(DBDatetime::ISO_DATETIME),
                ]);
                $record->write();
            }

            $ids = json_decode($record->IDs) ?: [];
            try {
                $update = new SolrUpdate();
                $update->setDebug(false);
                $update->updateItems($this->owner, SolrUpdate::UPDATE_TYPE);
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
     * @throws ValidationException
     */
    public function onAfterDelete(): void
    {
        /** @var DirtyClass $record */
        $record = DirtyClass::get()->filter(['Class' => $this->owner->ClassName])->first();
        if (!$record || !$record->exists()) {
            $record = DirtyClass::create([
                'Class' => $this->owner->ClassName,
                'Dirty' => DBDatetime::now()->Format(DBDatetime::ISO_DATETIME),
            ]);
            $record->write();
        }

        $ids = json_decode($record->IDs) ?: [];
        parent::onAfterDelete();
        try {
            (new SolrUpdate())->updateItems($this->owner, SolrUpdate::DELETE_TYPE);
            $record->Clean = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);
            $record->IDs = json_encode($ids);
        } catch (Exception $e) {
            $this->registerException($ids, $record, $e);
        }
        $record->write();
    }

    /**
     * @param array $ids
     * @param $record
     * @param Exception $e
     */
    protected function registerException(array $ids, $record, Exception $e): void
    {
        $ids[] = $this->owner->ID;
        // If we don't get an exception, mark the item as clean
        $record->Dirty = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);
        $record->IDs = json_encode($ids);
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->warn(
            sprintf(
                'Unable to alter %s with ID %s',
                $this->owner->ClassName,
                $this->owner->ID
            )
        );
        $logger->error($e->getMessage());
    }

    /**
     * Get the view status for each member in this object
     * @return array
     */
    public function getViewStatus(): array
    {
        if (array_key_exists($this->owner->ClassName, $this->canViewClasses) &&
            !$this->owner instanceof SiteTree
        ) {
            return $this->canViewClasses[$this->owner->ClassName];
        }
        $return = [];
        // Add null users if it's publicly viewable
        if ($this->owner->canView()) {
            $return = ['1-null'];

            return $return;
        }

        if (!self::$members) {
            self::$members = Member::get();
        }

        foreach (self::$members as $member) {
            $return[] = $this->owner->canView($member) . '-' . $member->ID;
        }

        // Dont record sitetree activity, it'll take up much needed memory
        if (!$this->owner instanceof SiteTree) {
            $this->canViewClasses[$this->owner->ClassName] = $return;
        }

        return $return;
    }
}
