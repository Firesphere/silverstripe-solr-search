# FAQ

### What do you mean not fast enough?

All indexing, as well as the search, require disk space. If the disk can not respond fast enough to a write,
either PHP or Java will stop and throw an error

### Do you support synonyms

Yes! Including US to UK spelling synonyms by default!

### Fast?

Yes, very fast

### Compatible with the Fulltext Search Module?

99% and counting, does that work for you? Have a look at the compatibility module

### Why do I need to name my index?

You have a name yourself, don't you? It makes sense to name the index too.

### Only File storage?

Hold your horses, this is a beta stage project, more storage options to come!

### My core.properties won't persist, what gives?

Most likely, you're running a vagrant or docker machine? And even more likely
running it on Linux.

The solution is to create a symlink in `/var/solr/data/{your-policy-name/` to `/var/www/{yoursite}/.solr/.conf`, so the
`conf` folder points to your locally writable folder for Vagrant.

In your search.yml, add the following as the location of your FileStore config:
```yaml
Firesphere\SolrSearch\Services\SolrCoreService:
  store:
    mode: 'file'
    path: '/var/solr/data'
```

That way, Solr will write it's own files to the correct core folder where it can write, but your config can still live
inside your project.

### I would like a feature to be added!

I would like an issue to be created

### Dealing with errors

Especially when using facets, you should not redeclare fields to be filterable as well. If you run in to an error 
saying you have duplicate fields, check your configuration that e.g. FilterFields does not have an overlap with FacetFields, etc.

# GitStore

As you may know, Open Source Software is written by volunteers. We volunteer our time, to work
on software a lot.

But, by doing such, we do not have time for other things to relax. Which causes friction.

If you can afford it, we would appreciate it a lot if you would sponsor us through GitStore.

## But your software is free and open

Yes, but that does not mean it doesn't cost us time to respond to issues, look at pull requests
or even support you with problems outside of the module. (A common one is Solr and Linux permission failures).

We do not mind helping you. But we have put a lot of time and effort in to writing software
that is useful to you, the community. We ask you, could you give something back? It is not mandatory,
of course.

We will add you to the list of sponsors, if you decide to pay for this work.