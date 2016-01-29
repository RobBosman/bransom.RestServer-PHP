<?php

$output = '<h3>create</h3>';

// create single object with/without temporary id
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create single object without temporary id',
        '<site />',
        '<set><site><id>1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create single object with temporary id',
        '<site id="T0" />',
        '<set><site><id temporary="T0">1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

// create multiple nested objects with/without nested created flag, with/without temporary id, with/without ownerId
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create multiple nested objects without nested created flag, without temporary id, without ownerId',
        '<site><itemset /></site>',
        '<set><itemset><id>2</id></itemset><site><id>1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]/itemset[2]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<itemset id="2" published="false"\s*/>\s*'
        . '</site>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create multiple nested objects without nested created flag, with temporary id, without ownerId',
        '<site created="true" id="T0"><itemset id="T1" /></site>',
        '<set><itemset><id temporary="T1">2</id></itemset><site><id temporary="T0">1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]/itemset[2]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<itemset id="2" published="false"\s*/>\s*'
        . '</site>');

// This test is a 'Bad Request'.
//$output .= $testEngine->assertPostXml(
//        'create multiple nested objects without nested created flag, without temporary id, with ownerId',
//        '<site><itemset ownerId="1" /></site>',
//        '<set><site><id>[0-9]+</id></site><itemset><id>[0-9]+</id></itemset></set>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create multiple nested objects without nested created flag, with temporary id, with ownerId',
        '<site created="true" id="T0"><itemset id="T1" ownerId="T0" /></site>',
        '<set><itemset><id temporary="T1">2</id></itemset><site><id temporary="T0">1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]/itemset[2]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<itemset id="2" published="false"\s*/>\s*'
        . '</site>');

// create child object; child holds foreign key, with/without temporary id, with/without ownerId
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create child object; child holds foreign key, without temporary id, without ownerId',
        '<itemset created="true" />',
        '<set><itemset><id>1</id></itemset></set>');
$output .= $testEngine->assertGetXml('get itemset[1]', $url . '/itemset/1',
        '<itemset xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create child object; child holds foreign key, with temporary id, without ownerId',
        '<itemset created="true" id="T1" />',
        '<set><itemset><id temporary="T1">1</id></itemset></set>');
$output .= $testEngine->assertGetXml('get itemset[1]', $url . '/itemset/1',
        '<itemset xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create site parent object for following tests',
        '<site created="true" />',
        '<set><site><id>1</id></site></set>');
$output .= $testEngine->assertGetXml('get site[1]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$output .= $testEngine->assertPostXml(
        'create child object; child holds foreign key, without temporary id, with ownerId',
        '<itemset created="true" ownerId="1" />',
        '<set><itemset><id>2</id></itemset></set>');
$output .= $testEngine->assertGetXml('get site[1]/itemset[2]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<itemset id="2" published="false"\s*/>\s*'
        . '</site>');

$output .= $testEngine->assertPostXml(
        'create child object; child holds foreign key, with temporary id, with ownerId',
        '<itemset created="true" id="T2" ownerId="1" />',
        '<set><itemset><id temporary="T2">3</id></itemset></set>');
$output .= $testEngine->assertGetXml('get site[1]/itemset[2+3]', $url . '/site/1',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<itemset id="2" published="false"\s*/>\s*'
        . '<itemset id="3" published="false"\s*/>\s*'
        . '</site>');

// create child object; parent holds foreign key, with/without temporary id, with/without ownerId
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create child object; parent holds foreign key, without temporary id, without ownerId',
        '<settings_a created="true" />',
        '<set><settings_a><id>1</id></settings_a></set>');
$output .= $testEngine->assertGetXml('get settings_a[1]', $url . '/settings_a/1',
        '<settings_a xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create child object; parent holds foreign key, with temporary id, without ownerId',
        '<settings_a created="true" id="T3" />',
        '<set><settings_a><id temporary="T3">1</id></settings_a></set>');
$output .= $testEngine->assertGetXml('get settings_a[1]', $url . '/settings_a/1',
        '<settings_a xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create itemset parent object for following tests',
        '<itemset created="true" />',
        '<set><itemset><id>1</id></itemset></set>');
$output .= $testEngine->assertGetXml('get itemset[1]', $url . '/itemset/1',
        '<itemset xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*"\s*/>');

$output .= $testEngine->assertPostXml(
        'create child object; parent holds foreign key, without temporary id, with ownerId',
        '<settings_a created="true" ownerId="1" />',
        '<set><settings_a><id>2</id></settings_a></set>');
$output .= $testEngine->assertGetXml('get itemset[1]/settings_a[2]', $url . '/itemset/1',
        '<itemset xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<settings_a id="2" published="false"\s*/>\s*'
        . '</itemset>');

$output .= $testEngine->assertPostXml(
        'create child object; parent holds foreign key, with temporary id, with ownerId',
        '<settings_a created="true" id="T3" ownerId="1" />',
        '<set><settings_a><id temporary="T3">4</id></settings_a></set>');
$output .= $testEngine->assertGetXml('get itemset[1]/settings_a[4]', $url . '/itemset/1',
        '<itemset xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="1" published="false" at=".*">\s*'
        . '<settings_a id="4" published="false"\s*/>\s*'
        . '</itemset>');

// create multiple non-nested objects
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create two sites',
        '<set><site created="true"><name>site_1</name></site><site created="true"><name>site_2</name></site></set>',
        '<set><site><id>1</id><id>2</id></site></set>');
$output .= $testEngine->assertGetXml('get sites', $url . '/site',
		'<set-of-site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" published="false" at=".*">'
		. '<site id="1" published="false"><name><![CDATA[site_1]]></name></site>'
		. '<site id="2" published="false"><name><![CDATA[site_2]]></name></site>'
		. '</set-of-site>');

return $output;

?>