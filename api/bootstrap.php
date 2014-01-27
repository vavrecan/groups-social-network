<?php

define("APP_DIR", __DIR__);
define("LIBS_DIR", __DIR__ . "/libs");
define("TEMP_DIR", __DIR__ . "/temp");

require_once(__DIR__ . "/include/api.php");

$config = include("config.php");
$api = new Api($config);
$api->run();
