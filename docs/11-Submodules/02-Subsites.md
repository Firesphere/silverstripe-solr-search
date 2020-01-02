# [Subsites submodule](https://github.com/Firesphere/silverstripe-subsite-solr)

With the [Subsites submodule](https://github.com/Firesphere/silverstripe-subsite-solr), searching split between Subsites is automatically handled.

Indexing by SubsiteID etc. is also automatically added to the appropriate parts of the indexing.

When adding the Subsites module, you will need to do a full reindex of your Solr cores.
Otherwise, changes won't be applied and Solr might return unexpected results.
