<?xml version="1.0" encoding="UTF-8" ?>
<!-- Solr 7+ Implementation -->
<!--
 Licensed to the Apache Software Foundation (ASF) under one or more
 contributor license agreements.  See the NOTICE file distributed with
 this work for additional information regarding copyright ownership.
 The ASF licenses this file to You under the Apache License, Version 2.0
 (the "License"); you may not use this file except in compliance with
 the License.  You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
-->

<!--
 This is the Solr schema file. This file should be named "schema.xml" and
 should be in the conf directory under the solr home
 (i.e. ./solr/conf/schema.xml by default)
 or located where the classloader for the Solr webapp can find it.

 This example schema is the recommended starting point for users.
 It should be kept correct and concise, usable out-of-the-box.

 For more information, on how to customize this file, please see
 http://wiki.apache.org/solr/SchemaXml

 PERFORMANCE NOTE: this schema includes many optional features and should not
 be used for benchmarking.  To improve performance one could
  - set stored="false" for all fields possible (esp large fields) when you
    only need to search on the field but don't need to return the original
    value.
  - set indexed="false" if you don't need to search on the field, but only
    return the field as a result of searching on other indexed fields.
  - remove all unneeded copyField statements
  - for best index size and searching performance, set "index" to false
    for all general text fields, use copyField to copy them to the
    catchall "text" field, and use that for searching.
  - For maximum indexing performance, use the StreamingUpdateSolrServer
    java client.
  - Remember to run the JVM in server mode, and use a higher logging level
    that avoids logging every request
-->

<schema name="$IndexName" version="1.5">
    <types>
        $Types
    </types>
    <fields>
        <%-- Default fields, needed for all items --%>
        <field name="$IDField" type="string" indexed="true" stored="true" required="true"/>
        <field name="$ClassID" type="tint" indexed="true" stored="true" required="true"/>
        <field name="ClassName" type="string" indexed="true" stored="true" required="true"/>
        <field name="ClassHierarchy" type="string" indexed="true" stored="true" required="true" multiValued="true"/>
        <field name="ViewStatus" type="string" indexed="true" stored="true" required="true" multiValued="true"/>
        <field name="_version_" type="long" indexed="true" stored="true" multiValued="false"/>
        <!-- Copyfields -->
        <% loop $CopyFields %>
            <field name="$Field" type="stemfield" indexed="true" stored="true" multiValued="true"/>
        <% end_loop %>
        <!-- End Copyfields -->
        <!-- Fulltext fields -->
        <% loop $FulltextFieldDefinitions %>
            <field name="$Field" type="$Type" indexed="$Indexed" stored="$Stored" multiValued="$MultiValued"/>
        <% end_loop %>
        <!-- End Fulltext fields -->

        <!-- Filter/Facet fields -->
        <% loop $FilterFieldDefinitions %>
            <field name="$Field" type="$Type" indexed="$Indexed" stored="$Stored" multiValued="$MultiValued" docValues="true"/>
        <% end_loop %>
        <!-- End Filter/Facet fields -->
    </fields>

    <% loop $CopyFieldDefinitions %>
        <copyField source="$Field" dest="$Destination"/>
    <% end_loop %>

    <uniqueKey>$IDField</uniqueKey>

    <df>$DefaultField</df>

    <solrQueryParser q.op="OR"/>
</schema>
