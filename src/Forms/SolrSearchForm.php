<?php


namespace Firesphere\SearchConfig\Forms;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\Validator;

class SolrSearchForm extends Form
{
    public function __construct(
        RequestHandler $controller = null,
        $name = self::DEFAULT_NAME,
        FieldList $fields = null,
        FieldList $actions = null,
        Validator $validator = null
    ) {
        $form = parent::__construct($controller, $name, $fields, $actions, $validator);

        $form->setFormMethod('GET');

        $form->disableSecurityToken();
    }
}
