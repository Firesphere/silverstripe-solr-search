# [Subsites submodule](https://github.com/Firesphere/silverstripe-subsite-solr)

With the [Subsites submodule](https://github.com/Firesphere/silverstripe-subsite-solr), searching split between Subsites is automatically handled.

`SubsiteID` is automatically added to the appropriate parts of the indexing, so
that results from each subsite are kept separated when searching.

When adding the Subsites module, you will need to do a full reindex of your Solr
cores. Otherwise, changes won't be applied and Solr might return unexpected results.
