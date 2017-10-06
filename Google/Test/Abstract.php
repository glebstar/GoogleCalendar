<?php

if (!defined("PATH_SEPARATOR")) {
    define(PATH_SEPARATOR, getenv("COMSPEC")? ";" : ":");
}

ini_set("include_path", ini_get("include_path") .   PATH_SEPARATOR . '/..' . dirname(__FILE__));

abstract class Google_Test_Abstract extends PHPUnit_Framework_TestCase
{
    
}

if ( !function_exists('__autoload') ) {
    function __autoload($class)
    {
        $class = str_replace('_', '/', $class);
        require_once($class . '.php');
    }
}

