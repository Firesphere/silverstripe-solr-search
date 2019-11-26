<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;

/**
 * Class \Firesphere\SolrSearch\Models\SearchSynonym
 *
 * @property string $Keyword
 * @property string $Synonym
 */
class SearchSynonym extends DataObject
{
    private static $table_name = 'SearchSynonym';

    private static $singular_name = 'Search synonym';

    private static $plural_name = 'Search synonyms';

    private static $db = [
        'Keyword' => 'Varchar(255)',
        'Synonym' => 'Text'
    ];

    private static $summary_fields = [
        'Keyword',
        'Synonym'
    ];

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
