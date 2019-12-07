# Viewing permissions

View permissions are indexed group based and setting based.

On page level, this means if it's public, the view will be indexed as `'null'`, meaning anyone can see.
Inheritance is calculated, and if it is logged in only, then only logged in members will be able to see it.

If it's specific groups, then these groups are indexed.

## More granular approaches

In case you have custom `canView()` implementations, we strongly suggest using `InheritedPermissionsExtension` and proper
permission implementations instead.

The reason for not using custom `Member` based permission checks, is because when a site has a lot of members, indexing
and the granular approach would become incredibly complex and long. Possibly causing errors during indexing.