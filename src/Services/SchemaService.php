<?php


namespace Firesphere\SearchConfig\Services;

use Firesphere\SearchConfig\Indexes\BaseIndex;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ViewableData;

class SchemaService extends ViewableData
{
    /**
     * @var string Path to template
     */
    protected $template;
    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * @return BaseIndex
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param BaseIndex $index
     * @return SchemaService
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    public function getFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFulltextFields() as $field => $options) {
            $isRelation = substr_count('_', $field);
            $item = [
                'Field'       => $field,
                'Type'        => 'text',
                'Indexed'     => 'true',
                'Stored'      => 'false',
                'MultiValued' => $isRelation > 1 ? 'true' : 'false'
            ];

            $return->push($item);
        }

        return $return;
    }

    public function getCopyFieldDefinitions()
    {
        $return = ArrayList::create();
        foreach ($this->index->getFulltextFields() as $field) {
            $item = [
                'Field'       => $field,
                'Destination' => '_text'
            ];
            $return->push($item);
        }

        return $return;
    }

    public function generateSchema()
    {
        if (!$this->template) {
            // @todo configurable but with default to the current absolute path
            $dir = __DIR__;
            $dir = rtrim(substr($dir, 0, strpos($dir, 'searchconfig') + strlen('searchconfig')), '/');
            $this->setTemplate($dir . '/Solr/5/templates/schema.ss');
        }

        return $this->renderWith($this->getTemplate());
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     * @return SchemaService
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function getExtrasPath()
    {
        // @todo configurable but with default to the current absolute path
        $dir = __DIR__;
        $dir = rtrim(substr($dir, 0, strpos($dir, 'searchconfig') + strlen('searchconfig')), '/');

        $confDirs = SolrCoreService::config()->get('paths');

        return sprintf($confDirs['extras'], $dir);
    }
}
