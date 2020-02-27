# [Elemental indexes](https://github.com/dnadesign/silverstripe-elemental)

Indexing Elemental is quite tricky, as Elements are part of the content, but can also be standalone.

There are a few options to index Elements as part of the page:

## Through Elemental provided method

```yaml
Firesphere\SolrSearch\Indexes\BaseIndex:
  MyIndexName:
    Classes:
      - SilverStripe\CMS\Model\SiteTree
    FulltextFields:
      - Title
      - ElementsForSearch
```

## Index elements specifically

```yaml
Firesphere\SolrSearch\Indexes\BaseIndex:
  MyIndexName:
    Classes:
      - DNADesign\Elemental\Models\BaseElement
    FulltextFields:
      - Title
      - forTemplate
```

This will index Elements with their Title and rendered content.
