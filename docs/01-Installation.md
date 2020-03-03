# Installing the module

Best practice is to use composer:
`composer require firesphere/solr-search`

## Manual setup

- Create a clean installation of SilverStripe 4 (`composer create-project`)
- Clone this repo in to the folder of your liking
- Check which modules you need to add to your base `composer.json`
- Run a composer update
- Create a base index:
```php
class MyIndex extends BaseIndex
{
    /**
     * Called during construction, this is the method that builds the structure.
     * Used instead of overriding __construct as we have specific execution order - code that has
     * to be run before _and/or_ after this.
     * @throws Exception
     */
    public function init()
    {
        $this->addClass(SiteTree::class);

        $this->addFulltextField('Title');
    }
    
    public function getIndexName()
    {
        return 'this-is-my-index';
    }
}
```
- Run `vendor/bin/sake dev/tasks/SolrConfigureTask` to configure the core
- Run `vendor/bin/sake dev/tasks/SolrIndexTask` to add documents to your index

Once these tasks have completed - happy searching!
