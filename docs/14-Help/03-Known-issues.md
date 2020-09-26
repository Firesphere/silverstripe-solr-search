# Known issues

## Database errors

Did you do a build?

`https://yourdomain.com/dev/build?flush=all`

It is known that the final index might throw a MySQL Exception.
This is expected at the moment, and sadly, unavoidable so far.
If you have a solution, we would love to hear from you!

## Linux hosts

There is a known issue with Linux hosts where Solr does not have
the correct write permissions, and the web user does not have the correct write
permissions either.

This can be resolved by setting the folder of your Solr Core to `/var/solr/data`.

Then, create the following subfolders in the data folder:
- `YourCoreName/conf`
- `YourCoreName/data`

Then, add the `solr` user to the `apache` group (or `www-data`)
And the other way around, add `apache` to `solr`.

Change the ownership of the whole `YourCoreName` folder to `solr:apache`.

Change the permissions on `YourCoreName/conf` to be `777`.

This should, in theory, resolve your permission errors.

These errors are _not_ related to this module, but on how Vagrant is set up on Linux.

**NOTE**

The name of your web user could be different, so make sure you get it right.
After updating the group permissions, be sure to log out and back in again.

## Solr and permission issues

It's also known that Solr won't properly reload cores when the permissions are wrong. This is outside
of control for this module, it is advised to restart Solr before and after a config change.

The best way to set the permissions, is to execute the following commands on the Solr target folder (Default /var/solr).

```shell script
usermod -a -G www-data solr
groups solr
usermod -a -G solr www-data
groups www-data
chown -R solr:www-data /var/solr
chmod -R u+rwxs,g+rwxs /var/solr
```

Replace `www-data` with your own web user.

## Solr is running as the wrong user

For yet unknown reasons, even after following the Solr installation guide, it may happen
that Solr runs as the wrong user.

If this happens, the best course of action is to stop Solr and start it with the correct user:
`cd /opt/solr-8.3.1 && sudo -u solr ./bin/solr start`

Where `8.3.1` should reflect your current Solr version. Or, if you've allowed Solr to create
symlinks, the path could be `/opt/solr`.

## Facets do not show anymore since the latest version

Yep, the `XML` switched to non-deprecated options, which causes facets and filters to
not work properly anymore.
Please re-index your Solr Core;
`vendor/bin/sake dev/tasks/SolrConfigureTask flush=all` followed
by `vendor/bin/sake dev/tasks/SolrIndexTask` from terminal is the most efficient way.

This is caused by a deprecated change in the Integer field on Solr level and can not be
fixed in any other way.

## Localhost?

Yes, for now, the config requires the host's name to be `localhost`. This is not exactly by choice,
but due to how Solarium works. Stay tuned for updates.

## My config is written to the wrong folder (`.solr`)

This is probably due to an older version of this module that had this bug. Please upgrade.

## I can't get it to work on Live or UAT

We're very sorry, but deployments of Solr differ so much per deployment, that we can't
give any solid advice.

The best answer right now is following these steps:
- Install Solr as described in [Install Solr](../02-Solr.md)
- Make sure the `data` folder is executable fully by Solr (`rxd` on Linux)
- Make sure the `data/conf` folder is writeable by your PHP user and the webserver
 (Apache2/Nginx/Caddy/Lighttpd etc.)
- Run a local test of the live environment, ensuring all configs are good to go
- If there are still errors, please contact your own sysadmin team first. They are most
likely to be able to resolve the problem

## Some groups give an error about `P` or `G`

Yes, a head-scratcher! Re-run that specific group `SolrIndexTask group={Group Number}` and all should be fine.

Sometimes, for an unknown reason, the `POST` or `GET` part of the request string is included in the XML that is
sent to Solr.

### To no avail, no solution worked

If it is related to the module, and not related to actual permissions or other server set-up issues, and you
 can not make a public issue, you can contact us at
 
`solr[@]casa-laguna[.]net`

Please note that we may charge you for investigating and helping solve your issues. This module is FOSS, but
our time is not unlimited and we may feel an issue is not worth the effort unless we get paid for our time.
