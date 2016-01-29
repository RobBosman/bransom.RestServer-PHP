<?php

$output = '<h3>update</h3>';

// create start data
$testEngine->executeDatabaseScript($fillDatabaseScript);
$output .= $testEngine->assertPostXml(
        'create two sites',
        '<set>'
        . '<site><name>site_1</name></site>'
        . '<site><name>site_2</name><itemset><name>itemset_A</name></itemset></site>'
        . '</set>',
        '<set><itemset><id>3</id></itemset><site><id>1</id><id>2</id></site></set>');
$output .= $testEngine->assertGetXml('get sites', $url . '/site',
	'<set-of-site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" published="false" at=".*">'
	. '<site id="1" published="false"><name><![CDATA[site_1]]></name></site>'
	. '<site id="2" published="false">'
        . '<itemset id="3" published="false"><name><![CDATA[itemset_A]]></name></itemset>'
        . '<name><![CDATA[site_2]]></name></site>'
	. '</set-of-site>');

// update single object
$output .= $testEngine->assertPostXml(
        'update single object',
        '<site id="1"><name>site_A</name></site>',
        '<set/>');
$output .= $testEngine->assertGetXml('get sites', $url . '/site',
        '<set-of-site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" published="false" at=".*">'
        . '<site id="1" published="false"><name><![CDATA[site_A]]></name></site>'
        . '<site id="2" published="false">'
        . '<itemset id="3" published="false"><name><![CDATA[itemset_A]]></name></itemset>'
        . '<name><![CDATA[site_2]]></name></site>'
	. '</set-of-site>');

// update multiple non-nested objects
$output .= $testEngine->assertPostXml(
        'update multiple non-nested objects',
        '<set>'
        . '<site id="1"><name>site_P</name></site>'
        . '<site id="2"><name>site_Q</name></site>'
        . '</set>',
        '<set/>');
$output .= $testEngine->assertGetXml('get sites', $url . '/site',
        '<set-of-site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" published="false" at=".*">'
        . '<site id="1" published="false"><name><![CDATA[site_P]]></name></site>'
        . '<site id="2" published="false">'
        . '<itemset id="3" published="false"><name><![CDATA[itemset_A]]></name></itemset>'
        . '<name><![CDATA[site_Q]]></name></site>'
        . '</set-of-site>');

// update multiple nested objects
$output .= $testEngine->assertPostXml(
        'update multiple nested objects',
        '<set>'
        . '<site id="2"><name>site_Y</name>'
        . '<itemset id="3"><name><![CDATA[itemset_Z]]></name></itemset>'
        . '</site>'
        . '</set>',
        '<set/>');
$output .= $testEngine->assertGetXml('get site[2]', $url . '/site/2.xml',
        '<site xmlns="http://ns.bransom.nl/vanace/webitems/v20110101" id="2" published="false" at=".*">'
        . '<itemset id="3" published="false"><name><![CDATA[itemset_A]]></name></itemset>'
        . '<name><![CDATA[site_Y]]></name>'
        . '</site>');

return $output;

?>