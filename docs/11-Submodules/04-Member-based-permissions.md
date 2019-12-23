# [Member based indexing](https://github.com/Firesphere/silverstripe-solr-member-permissions)

By default, indexing of permissions is based on groups. If you want or need member specific, say, for each member
the index should check if the permission is correct, you'll need the [Member based submodule](https://github.com/Firesphere/silverstripe-solr-member-permissions).

**IMPORTANT**

This submodule is work in progress

## Functionality

This module removes the filtering on Groups and replaces it with per-member statusses, to validate if a member
currently logged in can view objects at index time.

During query time, this module replaces the Group based view filter with Member specific view status checks.

## Notes

This module is not the suggested way to filter by view status. Group view is a lot more effecient.
