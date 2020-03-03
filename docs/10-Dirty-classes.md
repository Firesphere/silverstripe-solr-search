# Dirty Classes

The module keeps a record of the classes that failed to successfully (re-)index.
To manage these classes, there is a `ClearDirtyClassesJob` and corresponding `ClearDirtyClassesTask`.
The task should run on a regular basis, depending on how often items in the index are updated
in the CMS. The job is to make it easier for CMS-users to execute the task.
