# Spellcheck

> I cand spel gud

Spellchecking is enabled by default, and can be disabled
on query time, by setting `$query->setSpellcheck(false);`

Spellchecking is carried over to the search result returned.

To access the spellchecks, the following methods can be used:

## Word-based only spellchecking

Word based spellcheck returns only misspelled words. For example,
if the query is "hesp me", the word based spellcheck will return a list
of words that are possible alternatives for "hesp".

e.g.
- help
- helm
- hero

The resulting list can be accessed as an ArrayList, as the example below:

```html
<% if $Results.Spellcheck.Count %>
    <% loop $Results.Spellcheck %>
        $word
    <% end_loop %>
<% end_if %>
```

## Collated spellchecking

Collated spellcheck, is the full term, but spell checked.

For example searching for "hesp me", the collation would be "help me"

The collated spellcheck results can be displayed like so:
```html
<% with $Results %>
    <% if $getCollatedSpellcheck %>$CollatedSpellcheck<% end_if %>
<% end_with %>
```
