<?php

require_once realpath(dirname(__FILE__) . '/../../../TestEngine.class.php');

//set_time_limit(60);

TestEngine::createDatabaseSchema('rest_test');
$url = 'http://localhost:9080/webitems/REST/rest_test';
$testEngine = new TestEngine('rest_test', $url, array('user' => 'Rob'));
$testEngine->executeDatabaseScript(file_get_contents('create_tables-rest_test.sql'));
$fillDatabaseScript = file_get_contents('fill_db-rest_test.sql');

require 'testCreate.php';
echo $output;

require 'testUpdate.php';
echo $output;

//require 'testDelete.php';
//echo $output;

?>