[![Maintainability](https://api.codeclimate.com/v1/badges/55c8967ef25e37182e3d/maintainability)](https://codeclimate.com/github/Firesphere/silverstripe-solr-search/maintainability)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Firesphere/silverstripe-solr-search/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Firesphere/silverstripe-solr-search/?branch=master)
[![CircleCI](https://circleci.com/gh/Firesphere/silverstripe-solr-search/tree/master.svg?style=svg)](https://circleci.com/gh/Firesphere/silverstripe-solr-search/tree/master)
[![codecov](https://codecov.io/gh/Firesphere/silverstripe-solr-search/branch/master/graph/badge.svg)](https://codecov.io/gh/Firesphere/silverstripe-solr-search)
[![Test Coverage](https://api.codeclimate.com/v1/badges/55c8967ef25e37182e3d/test_coverage)](https://codeclimate.com/github/Firesphere/silverstripe-solr-search/test_coverage)
[![Build Status](https://scrutinizer-ci.com/g/Firesphere/silverstripe-solr-search/badges/build.png?b=master)](https://scrutinizer-ci.com/g/Firesphere/silverstripe-solr-search/build-status/master)


# Modern SilverStripe Solr Search

Full documentation in the docs folder

## Solarium documentation:
https://solarium.readthedocs.io

## API Docs:
https://firesphere.github.io/solr/

# Installing Solr locally

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

This will install Solr 8.x as a service on your (virtual) machine

## Supports

Solr4 backward compatibility is available, default support is
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

More details can be found in the docs.

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
