<?php


namespace Firesphere\SolrSearch\Forms;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\Validator;

/**
 * Class SolrSearchForm
 * Basic form to start searching
 *
 * @package Firesphere\SolrSearch\Forms
 */
class SearchForm extends Form
{
    /**
     * SolrSearchForm constructor.
     *
     * @param RequestHandler|null $controller
     * @param string $name
     * @param FieldList|null $fields
     * @param FieldList|null $actions
     * @param Validator|null $validator
     */
    public function __construct(
        RequestHandler $controller = null,
        $name = self::DEFAULT_NAME,
        FieldList $fields = null,
        FieldList $actions = null,
        Validator $validator = null
    )
    {
        parent::__construct($controller, $name, $fields, $actions, $validator);

        $this->setFormMethod('GET');

        $this->disableSecurityToken();
    }
}
