# Changelog

## 1.0.1

- Add authentication

## 1.0 Release Candidate

Bugfixes:
- Fix case sensitivity
- Fix Fluent submodule bugs
- Fix Subsite submodule bugs
- Make types and schema more easily overridable
- Prefix tables to prevent database clashes
- Updated documentation further
- Add config option to force debugging to be off
- Extracted logging
- Added docblock for `_config.php`

## 1.0 beta

During the beta period, the following issues have been resolved and needed features fixed. 
During the Release Candidate period, no new features will be accepted in to the main branch, only bugfixes.

- Fixes to the CMS Index Job.
- Fix issue #140 User not reset correctly.
- Fix issue #143 Not showing in search wasn't cleaned out correctly.
- Improved documentation.
- Fix stemming issues not properly stemming.
- Fix test errors.
- Fix `schema.xml` construction.
- [Change of Permission indexing from User based to Group based](../10-View-Permissions.md).
- Improved documentation.
- Removed history, moving to a later phase to include in a sub-module.
- Fix issues encountered for Dirty classes and Indexing
- Improved `PCNTL` detection for the CMS run of the Indexing job
- Added member-level module for per-member permission checks
- Fixed up multiple issues around the indexing on `ShowInSearch`
- Update to the docs to clarify the use of `ShowInSearch`
- Updated submodule documentation
- Improved debugging documentation
- Improved Linux based hosts Solr/Apache permission documentation
- Throw correct error when Solr has an issue #183 
- Created submodule for member specific indexing

**Thanks**

[Remy](https://github.com/rvxd)

[Elliot](https://github.com/elliot-sawyer)

[Russ](https://github.com/phptek)

## 0.9.x

- Documentation updates.
- Bump the version to leave bugs behind.

## 0.8.x

- Use multi-threaded indexing.
- Fix error with buffer adding.
- Initial Synonym support.

## 0.7.5 Notable updates

- Added `Add[X]Fields()`.
- Fix spellcheck retry.
- Increased test coverage.
- Support for basic Fluent (select your language for each indexing run).
- Documentation.
- Extracted compatibility to separate module.

## 0.5 First release

- Use Solarium.
- Support Facets.
- Support terms.
- Support boosting.
- Support filtering/excluding.
- Support highlighting.
- Support elevation.
- Simplified API.
- Fulltext Search compatibility.
