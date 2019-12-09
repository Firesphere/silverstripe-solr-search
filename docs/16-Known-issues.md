# Known issues

## Database errors

Did you do a build?

`https://yourdomain.com/dev/build?flush=all`

It is known that the final index might throw a MySQL error.
This is expected at the moment, and sadly, unavoidable so far.
If you have a solution, we would love to hear from you!


## Linux hosts with Vagrant

There is a known issue between Linux hosts using Vagrant. Solr does not have
the correct write permissions, and Apache does not have the correct write permissions either.

This can be resolved by setting the folder of your Solr Core to `/var/solr/data`.

Then, create the following subfolders in the data folder:
- `YourCoreName/conf`
- `YourCoreName/data`

Then, add the `solr` user to the `apache` group (or `www-data`)
And the other way around, add apache to solr.

Change the ownership of the whole `YourCoreName` folder to `solr:solr`.

Change the permissions on `YourCoreName/conf` to be `777`.

This should, in theory, resolve your permission errors.

These errors are _not_ related to this module, but on how Vagrant is set up on Linux.

## Solr and Vagrant issues, pt. 2

It's also known that Solr won't properly reload cores on Vagrant VM's. This is outside
of control for this module, it is advised to restart Solr before and after a config change.
