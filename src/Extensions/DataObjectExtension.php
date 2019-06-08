<?php


namespace Firesphere\SolrSearch\Extensions;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use ReflectionClass;
use ReflectionException;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

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

    protected $canViewClasses = [];

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $key = $this->owner->ClassName . '-' . $this->owner->ID;
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterDelete(): void
    {
        parent::onAfterDelete();

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);

        foreach ($indexes as $index) {
            // Skip the abstract base
            $ref = new ReflectionClass($index);
            if (!$ref->isInstantiable()) {
                continue;
            }

            /** @var BaseIndex $index */
            $index = Injector::inst()->get($index);
            // No point in sending a delete for something that's not in the index
            // @todo check the hierarchy, this could be a parent that should be indexed
            if (in_array($this->owner->ClassName, $index->getClasses(), true)) {
                $client = $index->getClient();

                try {
                    // get an update query instance
                    $update = $client->createUpdate();

                    // add the delete query and a commit command to the update query
                    $update->addDeleteQuery('id:' . $this->owner->ClassName . '-' . $this->owner->ID);
                    $update->addCommit();
                } catch (Exception $e) {
                    // Continue, this document doesn't exist, ignore it :)
                    // Or Solr is having a hickup, should be fine :)
                    continue;
                }
            }
        }
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
