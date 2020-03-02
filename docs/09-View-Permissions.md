# Viewing permissions

View permissions are indexed group-based and settings-based.

At page-level, this means if it's a public page, the view will be indexed as `'null'`, meaning anyone can see it.
Inheritance is then calculated, and if it is logged-in only, then only logged-in members will be able to see it.

If it's specific groups, then these groups are indexed accordingly.

## More granular approaches

If you have custom `canView()` implementations, we strongly suggest using `InheritedPermissionsExtension` and
proper permission implementations instead.

The reason for not using custom `Member`-based permission checks is that when a site has a lot of members, indexing
and the more granular approach would become incredibly complex and slow - increasing the possibility of
errors during indexing.
