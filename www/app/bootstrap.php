<?php

define("APP_DIR", __DIR__);
define("LIBS_DIR", __DIR__ . "/libs");
define("TEMPLATES_DIR", __DIR__ . "/templates");
define("TEMP_DIR", __DIR__ . "/temp");

date_default_timezone_set("Europe/Berlin");

require(__DIR__ . "/include/site.php");

$config = include("config.php");
$site = new Site($config);
$site->run();
