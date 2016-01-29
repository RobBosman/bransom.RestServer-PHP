<?php

error_reporting(E_ALL | E_NOTICE | E_WARNING | E_STRICT);

require_once realpath(dirname(__FILE__) . '/../php/Bootstrap.class.php');
Bootstrap::initConfig(dirname(__FILE__) . '/../config/config.ini');
Bootstrap::import('nl.bransom.http.HttpRequestHandler');
Bootstrap::import('nl.bransom.http.HttpResponder');

HttpResponder::handleFatalErrorsOnShutdown();

$restTarget = HttpRequestHandler::getRestTarget($_SERVER['REQUEST_URI'], $_SERVER['PHP_SELF']);
$requestContent = file_get_contents('php://input');
$requestContentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : NULL;

$requestHandler = new HttpRequestHandler();
$requestHandler->dispatchRequest($_SERVER['REQUEST_METHOD'], $restTarget, $_SERVER['QUERY_STRING'],
        $_SERVER['HTTP_ACCEPT'], NULL, $_REQUEST, $requestContent, $requestContentType);

?>