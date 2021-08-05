# [Member-based indexing](https://github.com/Firesphere/silverstripe-solr-member-permissions)

By default, permissions on indexes are based on groups. If you want the index to check the
permissions for each member, you'll need the [Member-based submodule](https://github.com/Firesphere/silverstripe-solr-member-permissions).

## Functionality

This module removes the filtering on Groups and replaces it with per-member statuses, to
validate if the member currently logged-in can view the objects at index time.

At query time, this module replaces the Group-based view filter with Member-specific
view status checks.

## Notes

This module is not the suggested way to filter by view status. Group view is a lot more efficient.
