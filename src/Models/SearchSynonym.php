<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;

/**
 * Class \Firesphere\SolrSearch\Models\SearchSynonym
 * Manageable synonyms in the CMS
 *
 * @package Firesphere\SolrSerach\Models
 * @property string $Keyword
 * @property string $Synonym
 */
class SearchSynonym extends DataObject
{
    /**
     * @var string Table name
     */
    private static $table_name = 'SearchSynonym';

    /**
     * @var string Singular name
     */
    private static $singular_name = 'Search synonym';

    /**
     * @var string Plural name
     */
    private static $plural_name = 'Search synonyms';

    /**
     * @var array DB Fields
     */
    private static $db = [
        'Keyword' => 'Varchar(255)',
        'Synonym' => 'Text'
    ];

    /**
     * @var array Summary fields
     */
    private static $summary_fields = [
        'Keyword',
        'Synonym'
    ];

    /**
     * Get the required CMS Fields for this synonym
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->dataFieldByName('Synonym')->setDescription(
            _t(
                __CLASS__ . '.SYNONYM',
                'Create synonyms for a given keyword, add as many synonyms comma separated.'
            )
        );

        return $fields;
    }
}
