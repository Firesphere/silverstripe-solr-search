# Modern SilverStripe Solr Search

Readme and docs to be completed at a later stage. Currently under heavy development.

Solarium documentation:
https://solarium.readthedocs.io

## Schema management

This module uses a managed schema.

# Installing Solr

## Debian Jessie

Debian Jessie needs backports to get Java 8 working:
```bash
echo "deb [check-valid-until=no] http://archive.debian.org/debian jessie-backports main" > /etc/apt/sources.list.d/jessie-backports.list
apt-get update
apt-get install -t jessie-backports openjdk-8-jre
```

If you run in to trouble updating, add the following to `/etc/apt/apt.conf`:
`Acquire::Check-Valid-Until "false";`


## Downloading and installing

Taken from https://lucene.apache.org/solr/guide/7_7/taking-solr-to-production.html

Update to match the required version.
```bash
wget http://www.apache.org/dyn/closer.lua/lucene/solr/8.1.0/solr-8.1.0.tgz # find your local URL manually
tar xvf solr-8.1.0.tgz solr-8.1.0/bin/install_solr_service.sh --strip-components=2
sudo bash ./install_solr_service.sh solr-8.1.0.tgz
```

This will install Solr 8.1 as a service on your (virtual) machine

### Pros

- Schema can be altered on the fly
- Schema management is connected to the CMS (TDB)

### Cons

- Managed schema changes currently get destroyed on a reconfigure

## Supports

Solr6 or higher

# Test setup

- Create a clean installation of SilverStripe 4 (`composer create-project`)
- Clone this repo in to the folder of your likings
- add `"solarium/solarium": "^4.2"` to your base composer.json
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
```
- Run `vendor/bin/sake dev/tasks/SolrConfigureTask` to configure the core
- Run `vendor/bin/sake dev/tasks/SolrIndexTask` to add documents to your index

Happy searching after that... once this is done

# FAQ

### Why index from bottom to top?

More recent items are often more important than old items. Therefore, it makes sense to make sure
the newer items are in the index, before the old ones.

This is because a limitation of both PHP and Java, not being able to write to disk fast enough.

### What do you mean not fast enough?

All indexing, as well as the search, require disk space. If the disk can not respond fast enough to a write,
either PHP or Java will stop and throw an error

### Do you support synonyms

Yes! Including US to UK spelling synonyms by default!

### Fast?

Yes, very fast

### Compatible with the Fulltext Search Module?

99% and counting, does that work for you?

### Why do I need to name my index?

You have a name yourself, don't you? It makes sense to name the index too.

### Only File storage?

Hold your horses, this is a beta stage project, more storage options to come!

### I would like a feature to be added!

I would like an issue to be created

# Cow?

Cow!

```

             /( ,,,,, )\
            _\,;;;;;;;,/_
         .-"; ;;;;;;;;; ;"-.
         '.__/`_ / \ _`\__.'
            | (')| |(') |
            | .--' '--. |
            |/ o     o \|
            |           |
           / \ _..=.._ / \
          /:. '._____.'   \
         ;::'    / \      .;
         |     _|_ _|_   ::|
       .-|     '==o=='    '|-.
      /  |  . /       \    |  \
      |  | ::|         |   | .|
      |  (  ')         (.  )::|
      |: |   |;  U U  ;|:: | `|
      |' |   | \ U U / |'  |  |
      ##V|   |_/`"""`\_|   |V##
         ##V##         ##V##
```
