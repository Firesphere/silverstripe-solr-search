<!-- The StrField type is not analyzed, but indexed/stored verbatim. -->
<fieldType name="string" class="solr.StrField" sortMissingLast="true" omitNorms="false"/>

<!-- boolean type: "true" or "false" -->
<fieldType name="boolean" class="solr.BoolField" sortMissingLast="true" omitNorms="false"/>
<!--Binary data type. The data should be sent/retrieved in as Base64 encoded Strings -->
<fieldtype name="binary" class="solr.BinaryField"/>

<!-- The optional sortMissingLast and sortMissingFirst attributes are
     currently supported on types that are sorted internally as strings.
       This includes "string","boolean","sint","slong","sfloat","sdouble","pdate"
   - If sortMissingLast="true", then a sort on this field will cause documents
     without the field to come after documents with the field,
     regardless of the requested sort order (asc or desc).
   - If sortMissingFirst="true", then a sort on this field will cause documents
     without the field to come before documents with the field,
     regardless of the requested sort order.
   - If sortMissingLast="false" and sortMissingFirst="false" (the default),
     then default lucene sorting will be used which places docs without the
     field first in an ascending sort and last in a descending sort.
-->

<!--
  Default numeric field types. For faster range queries, consider the tint/tfloat/tlong/tdouble types.
-->
<fieldType name="int" class="solr.IntPointField" omitNorms="false" positionIncrementGap="0" docValues="true"/>
<fieldType name="float" class="solr.FloatPointField" omitNorms="false" positionIncrementGap="0"/>
<fieldType name="long" class="solr.LongPointField" omitNorms="false" positionIncrementGap="0"/>
<fieldType name="double" class="solr.DoublePointField" omitNorms="false" positionIncrementGap="0"/>

<!--
 Numeric field types that index each value at various levels of precision
 to accelerate range queries when the number of values between the range
 endpoints is large. See the javadoc for NumericRangeQuery for internal
 implementation details.

 Smaller precisionStep values (specified in bits) will lead to more tokens
 indexed per value, slightly larger index size, and faster range queries.
 A precisionStep of 0 disables indexing at different precision levels.
-->
<fieldType name="tint" class="solr.IntPointField" omitNorms="false" positionIncrementGap="0" docValues="true"/>
<fieldType name="tfloat" class="solr.FloatPointField" omitNorms="false" positionIncrementGap="0"/>
<fieldType name="tlong" class="solr.LongPointField" omitNorms="false" positionIncrementGap="0"/>
<fieldType name="tdouble" class="solr.DoublePointField" omitNorms="false" positionIncrementGap="0"/>

<!-- The format for this date field is of the form 1995-12-31T23:59:59Z, and
     is a more restricted form of the canonical representation of dateTime
     http://www.w3.org/TR/xmlschema-2/#dateTime
     The trailing "Z" designates UTC time and is mandatory.
     Optional fractional seconds are allowed: 1995-12-31T23:59:59.999Z
     All other components are mandatory.

     Expressions can also be used to denote calculations that should be
     performed relative to "NOW" to determine the value, ie...

           NOW/HOUR
              ... Round to the start of the current hour
           NOW-1DAY
              ... Exactly 1 day prior to now
           NOW/DAY+6MONTHS+3DAYS
              ... 6 months and 3 days in the future from the start of
                  the current day

     Consult the DateField javadocs for more information.

     Note: For faster range queries, consider the tdate type
  -->
<fieldType name="date" class="solr.DatePointField" omitNorms="false" positionIncrementGap="0"/>

<!-- A Trie based date field for faster date range queries and date faceting. -->
<fieldType name="tdate" class="solr.DatePointField" omitNorms="false" positionIncrementGap="0"/>

<!-- The "RandomSortField" is not used to store or search any
     data.  You can declare fields of this type it in your schema
     to generate pseudo-random orderings of your docs for sorting
     purposes.  The ordering is generated based on the field name
     and the version of the index, As long as the index version
     remains unchanged, and the same field name is reused,
     the ordering of the docs will be consistent.
     If you want different psuedo-random orderings of documents,
     for the same version of the index, use a dynamicField and
     change the name
 -->
<fieldType name="random" class="solr.RandomSortField" indexed="true"/>

<!-- solr.TextField allows the specification of custom text analyzers
     specified as a tokenizer and a list of token filters. Different
     analyzers may be specified for indexing and querying.

     The optional positionIncrementGap puts space between multiple fields of
     this type on the same document, with the purpose of preventing false phrase
     matching across fields.

     For more info on customizing your analyzer chain, please see
     http://wiki.apache.org/solr/AnalyzersTokenizersTokenFilters
 -->

<!-- One can also specify an existing Analyzer class that has a
     default constructor via the class attribute on the analyzer element
<fieldType name="text_greek" class="solr.TextField">
  <analyzer class="org.apache.lucene.analysis.el.GreekAnalyzer"/>
</fieldType>
-->

<!-- A text field that only splits on whitespace for exact matching of words -->
<fieldType name="text_ws" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    </analyzer>
</fieldType>

<!-- A text field that uses WordDelimiterFilter to enable splitting and matching of
    words on case-change, alpha numeric boundaries, and non-alphanumeric chars,
    so that a query of "wifi" or "wi fi" could match a document containing "Wi-Fi".
    Synonyms and stopwords are customized by external files, and stemming is enabled.
    The attribute autoGeneratePhraseQueries="true" (the default) causes words that get split to
    form phrase queries. For example, WordDelimiterFilter splitting text:pdp-11 will cause the parser
    to generate text:"pdp 11" rather than (text:PDP OR text:11).
    NOTE: autoGeneratePhraseQueries="true" tends to not work well for non whitespace delimited languages.
    -->
<fieldType name="text" class="solr.TextField" positionIncrementGap="100" autoGeneratePhraseQueries="true">
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <!-- in this example, we will only use synonyms at query time
        <filter class="solr.SynonymGraphFilterFactory" synonyms="index_synonyms.txt" ignoreCase="true" expand="false"/>
        -->
        <!-- Case insensitive stop word removal.
          add enablePositionIncrements=true in both the index and query
          analyzers to leave a 'gap' for more accurate phrase queries.
        -->
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.StopFilterFactory"
                ignoreCase="true"
                words="stopwords.txt"

        />
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="1"
                catenateNumbers="1" catenateAll="0" splitOnCaseChange="1"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
    <analyzer type="query">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.KeywordRepeatFilterFactory"/>
        <filter class="solr.StopFilterFactory"
                ignoreCase="true"
                words="stopwords.txt"

        />
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="0"
                catenateNumbers="0" catenateAll="0" splitOnCaseChange="1"/>
        <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
        <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
</fieldType>
<fieldType name="stemfield" class="solr.TextField" positionIncrementGap="100" autoGeneratePhraseQueries="true">
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
    <analyzer type="query">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
    </analyzer>
</fieldType>
<!-- A copy of text that has the HTMLStripCharFilterFactory as the first index analyzer, so that html can be provided -->
<fieldType name="htmltext" class="solr.TextField" positionIncrementGap="100" autoGeneratePhraseQueries="true">
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <charFilter class="solr.HTMLStripCharFilterFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="0" generateNumberParts="1"
                catenateWords="1"
                catenateNumbers="1" catenateAll="0" splitOnCaseChange="1"/>
        <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
    <analyzer type="query">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.KeywordRepeatFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="0"
                catenateNumbers="0" catenateAll="0" splitOnCaseChange="0"/>
        <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
        <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<!-- Less flexible matching, but less false matches.  Probably not ideal for product names,
     but may be good for SKUs.  Can insert dashes in the wrong place and still match. -->
<fieldType name="textTight" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="0" generateNumberParts="0"
                catenateWords="1"
                catenateNumbers="1" catenateAll="0"/>
        <filter class="solr.KeywordMarkerFilterFactory" protected="protwords.txt"/>
        <filter class="solr.EnglishMinimalStemFilterFactory"/>
        <!-- this filter can remove any duplicate tokens that appear at the same position - sometimes
             possible with WordDelimiterFilter in conjuncton with stemming. -->
        <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<!-- Text optimized for spelling corrections, with minimal alterations (e.g. no stemming) -->
<fieldType name="textSpell" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <tokenizer class="solr.StandardTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.LengthFilterFactory" min="4" max="20"/>
        <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<!-- Text optimized for spelling corrections, with minimal alterations (e.g. no stemming) but also html filtering -->
<fieldType name="textSpellHtml" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <charFilter class="solr.HTMLStripCharFilterFactory"/>
        <tokenizer class="solr.StandardTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.LengthFilterFactory" min="4" max="20"/>
        <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<!-- A general unstemmed text field - good if one does not know the language of the field -->
<fieldType name="textgen" class="solr.TextField" positionIncrementGap="100">
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="1"
                catenateNumbers="1" catenateAll="0" splitOnCaseChange="0"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
    <analyzer type="query">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory"
                ignoreCase="true"
                words="stopwords.txt"

        />
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="0"
                catenateNumbers="0" catenateAll="0" splitOnCaseChange="0"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>


<!-- A general unstemmed text field that indexes tokens normally and also
     reversed (via ReversedWildcardFilterFactory), to enable more efficient
 leading wildcard queries. -->
<fieldType name="text_rev" class="solr.TextField" positionIncrementGap="100">
    <analyzer type="index">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords.txt"/>
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="1"
                catenateNumbers="1" catenateAll="0" splitOnCaseChange="0"/>
        <filter class="solr.ReversedWildcardFilterFactory" withOriginal="true"
                maxPosAsterisk="3" maxPosQuestion="2" maxFractionAsterisk="0.33"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    </analyzer>
    <analyzer type="query">
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.StopFilterFactory"
                ignoreCase="true"
                words="stopwords.txt"

        />
        <filter class="solr.WordDelimiterGraphFilterFactory" generateWordParts="1" generateNumberParts="1"
                catenateWords="0"
                catenateNumbers="0" catenateAll="0" splitOnCaseChange="0"/>
        <filter class="solr.SynonymGraphFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<!-- charFilter + WhitespaceTokenizer  -->
<!--
<fieldType name="textCharNorm" class="solr.TextField" positionIncrementGap="100" >
  <analyzer>
    <charFilter class="solr.MappingCharFilterFactory" mapping="mapping-ISOLatin1Accent.txt"/>
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
  </analyzer>
</fieldType>
-->

<!-- This is an example of using the KeywordTokenizer along
     With various TokenFilterFactories to produce a sortable field
     that does not include some properties of the source text
  -->
<fieldType name="alphaOnlySort" class="solr.TextField" sortMissingLast="true" omitNorms="false">
    <analyzer>
        <!-- KeywordTokenizer does no actual tokenizing, so the entire
             input string is preserved as a single token
          -->
        <tokenizer class="solr.KeywordTokenizerFactory"/>
        <!-- The LowerCase TokenFilter does what you expect, which can be
             when you want your sorting to be case insensitive
          -->
        <filter class="solr.LowerCaseFilterFactory"/>
        <!-- The TrimFilter removes any leading or trailing whitespace -->
        <filter class="solr.TrimFilterFactory"/>
        <!-- The PatternReplaceFilter gives you the flexibility to use
             Java Regular expression to replace any sequence of characters
             matching a pattern with an arbitrary replacement string,
             which may include back references to portions of the original
             string matched by the pattern.

             See the Java Regular Expression documentation for more
             information on pattern and replacement string syntax.

             http://java.sun.com/j2se/1.5.0/docs/api/java/util/regex/package-summary.html
          -->
        <filter class="solr.PatternReplaceFilterFactory"
                pattern="([^a-z])" replacement="" replace="all"
        />
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<fieldtype name="phonetic" stored="false" indexed="true" class="solr.TextField">
    <analyzer>
        <tokenizer class="solr.StandardTokenizerFactory"/>
        <filter class="solr.DoubleMetaphoneFilterFactory" inject="false"/>
    </analyzer>
</fieldtype>

<fieldtype name="payloads" stored="false" indexed="true" class="solr.TextField">
    <analyzer>
        <tokenizer class="solr.WhitespaceTokenizerFactory"/>
        <!--
        The DelimitedPayloadTokenFilter can put payloads on tokens... for example,
        a token of "foo|1.4"  would be indexed as "foo" with a payload of 1.4f
        Attributes of the DelimitedPayloadTokenFilterFactory :
         "delimiter" - a one character delimiter. Default is | (pipe)
	 "encoder" - how to encode the following value into a playload
	    float -> org.apache.lucene.analysis.payloads.FloatEncoder,
	    integer -> o.a.l.a.p.IntegerEncoder
	    identity -> o.a.l.a.p.IdentityEncoder
            Fully Qualified class name implementing PayloadEncoder, Encoder must have a no arg constructor.
         -->
        <filter class="solr.DelimitedPayloadTokenFilterFactory" encoder="float"/>
    </analyzer>
</fieldtype>

<!-- lowercases the entire field value, keeping it as a single token.  -->
<fieldType name="lowercase" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <tokenizer class="solr.KeywordTokenizerFactory"/>
        <filter class="solr.LowerCaseFilterFactory"/>
        <filter class="solr.ASCIIFoldingFilterFactory"/>
        <filter class="solr.SnowballPorterFilterFactory"/>
    </analyzer>
</fieldType>

<fieldType name="text_path" class="solr.TextField" positionIncrementGap="100">
    <analyzer>
        <tokenizer class="solr.PathHierarchyTokenizerFactory"/>
    </analyzer>
</fieldType>

<!-- since fields of this type are by default not stored or indexed,
     any data added to them will be ignored outright.  -->
<fieldtype name="ignored" stored="false" indexed="false" multiValued="true" class="solr.StrField"/>

<!-- This point type indexes the coordinates as separate fields (subFields)
  If subFieldType is defined, it references a type, and a dynamic field
  definition is created matching *___<typename>.  Alternately, if
  subFieldSuffix is defined, that is used to create the subFields.
  Example: if subFieldType="double", then the coordinates would be
    indexed in fields myloc_0___double,myloc_1___double.
  Example: if subFieldSuffix="_d" then the coordinates would be indexed
    in fields myloc_0_d,myloc_1_d
  The subFields are an implementation detail of the fieldType, and end
  users normally should not need to know about them.
 -->
<fieldType name="point" class="solr.PointType" dimension="2" subFieldSuffix="_d"/>

<!-- A specialized field for geospatial search. If indexed, this fieldType must not be multivalued. -->
<fieldType name="location" class="solr.LatLonPointSpatialField"/>

<!--
 A Geohash is a compact representation of a latitude longitude pair in a single field.
 See http://wiki.apache.org/solr/SpatialSearch
-->
<fieldtype name="geohash" class="solr.LatLonPointSpatialField"/>
