<?php

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");
error_reporting(E_ALL);
ini_set("display_errors", 1);

function extract_feature($token, $list) {
    $featureList = array();
    $lastFeature = null;

    for ($i = 0; $i < count($list); $i++) {
        if ($list[$i]["token"] == $token) {
            $i += 1;
            $lastFeature = $list[$i]["value"]; // get second parameter
        }

        if ($lastFeature != null) {
            $featureList[$lastFeature][] = $list[$i];
        }
    }

    return $featureList;
}


function extract_permissions($list) {
    $return = array();

    // find $allowedColumns
    for ($i = 0; $i < count($list) - 2; $i++) {
        if ($list[$i]["token"] == T_VARIABLE && $list[$i]["value"] == "\$this" &&
            $list[$i + 2]["token"] == T_STRING && preg_match("/require(?P<type>.*)$/i", $list[$i + 2]["value"], $match)) {

            $type = strtolower($match["type"]);
            if ($type == "param")
                continue;

            $return[$type] = 1;
        }
    }

    return $return;
}

function extract_public($list) {
    $return = array();

    for ($i = 0; $i < count($list) - 2; $i++) {
        if ($list[$i]["token"] == T_VARIABLE && $list[$i]["value"] == "\$publicMethods" &&
            $list[$i + 1]["token"] == T_ARRAY) {

            $i += 2;
            while ($list[$i]["token"] == T_CONSTANT_ENCAPSED_STRING) {
                $paramName = $list[$i]["value"];
                $paramName = trim($paramName, "\"");
                $return[] = $paramName;
                $i++;
            }
        }
    }

    return $return;
}

function extract_params($list) {
    $return = array();

    // find $allowedColumns
    for ($i = 0; $i < count($list) - 2; $i++) {
        if ($list[$i]["token"] == T_VARIABLE && $list[$i]["value"] == "\$allowedColumns" &&
            $list[$i + 1]["token"] == T_ARRAY) {

            $i += 2;
            while ($list[$i]["token"] == T_CONSTANT_ENCAPSED_STRING) {
                $paramName = $list[$i]["value"];
                $paramName = trim($paramName, "\"");
                $return[$paramName] = array("required" => false);
                $i++;
            }
        }
    }

    // find $this->params["string"]
    for ($i = 0; $i < count($list) - 3; $i++) {
        if ($list[$i]["token"] == T_VARIABLE && $list[$i]["value"] == "\$this" &&
            $list[$i + 2]["token"] == T_STRING && $list[$i + 2]["value"] == "params") {

            if ($list[$i + 3]["token"] == T_CONSTANT_ENCAPSED_STRING) {
                $paramName = $list[$i + 3]["value"];
                $paramName = trim($paramName, "\"");

                $return[$paramName] = array("required" => false);
            }
        }
    }

    // find $this->requireParams(...)
    for ($i = 0; $i < count($list) - 3; $i++) {
        if ($list[$i]["token"] == T_VARIABLE && $list[$i]["value"] == "\$this" &&
            $list[$i + 2]["token"] == T_STRING && $list[$i + 2]["value"] == "requireParam") {

            if ($list[$i + 3]["token"] == T_ARRAY) {
                $i += 4;
                while ($list[$i]["token"] == T_CONSTANT_ENCAPSED_STRING) {
                    $paramName = $list[$i]["value"];
                    $paramName = trim($paramName, "\"");
                    $return[$paramName] = array("required" => true);
                    $i++;
                }
            }

            if ($list[$i + 3]["token"] == T_CONSTANT_ENCAPSED_STRING) {
                $paramName = $list[$i + 3]["value"];
                $paramName = trim($paramName, "\"");
                $return[$paramName] = array("required" => true);
            }
        }
    }

    return $return;
}

function parse_documentation($file) {
    $tokens = token_get_all(file_get_contents($file));
    $list = array();

    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] != T_WHITESPACE)
            $list[] = array("token" => $token[0], "value" => $token[1]);
    }

    // extract classes
    $classList = extract_feature(T_CLASS, $list);
    $result = array();

    foreach ($classList as $class => $list) {
        $sessionRequired = false;

        if ($list[1]["value"] == "LoggedUserMethodHandler" || $list[2]["value"] == "LoggedUserMethodHandler")
            $sessionRequired = true;

        $publicMethods = extract_public($list);

        // extract functions
        $className = strtolower($class);
        $functionList = extract_feature(T_FUNCTION, $list);

        foreach ($functionList as $function => $list) {
            $functionName = $function;

            // only be interested in .*Handler...
            if (!preg_match("/(?P<function>.*)Handler$/i", $functionName, $matches))
                continue;

            $functionName = $matches["function"];

            $params = array();

            if ($sessionRequired)
                $params += array("session" => array("required" => !in_array($functionName, $publicMethods)));

            $params += extract_params($list);
            $permissions = extract_permissions($list);

            if (in_array($functionName, $publicMethods))
                $permissions["public"] = 1;

            if ($sessionRequired)
                $permissions["user"] = 1;

            $result[$className][$functionName]["params"] = $params;
            $result[$className][$functionName]["permissions"] = $permissions;
        }
    }
    /*
    for ($i = 0; $i < count($list); $i++) {
        if ($list[$i] == "")
    }*/

    return $result;
}

$methodsPath = __DIR__ . "/../../include/methods";

$files = glob($methodsPath . "/*");
$documentation = array();

foreach ($files as $file) {
    $parsed = parse_documentation($file);
    $documentation += $parsed;
}

?>
<html>
<style>
    * {
        font-family: ubuntu, arial, tahoma;
        font-size: 11px;
        margin: 0;
        padding: 0;
    }

    body {
        padding: 10px;
    }

    h2 {
        font-size: 14px;
        margin-bottom: 0px;
        background: #333333;
        color: #ffffff;
        padding: 5px 10px;
    }

    h3 {
        font-size: 12px;
        background: #dedede;
    }

    h3 a {
        display: block;
        color: #000000;
        padding: 5px 10px;
        text-decoration: none;
    }

    .class {
        margin-bottom: 15px;
    }

    .function {
        margin-bottom: 1px;
        background: #eeeeee;
    }

    .parameter {
        padding: 5px 10px;
    }

    .permission {
        padding: 5px 10px;
        background: #BDBDBD;
        float: right;
    }
</style>
<body>
<?php
foreach ($documentation as $class => $functions) {
    print "<div class='class'>";
    print "<h2>$class</h2>";

    foreach ($functions as $function => $parameters) {
        print "<div class='function'>";

        foreach ($parameters["permissions"] as $permission => $value) {
            print "<div class='permission'>";
            print "<p>{$permission}</p>";
            print "</div>";
        }

        print "<h3><a href='#$class/$function' onclick='document.getElementById(\"$class/$function\").style.display = document.getElementById(\"$class/$function\").style.display == \"none\" ? \"block\" : \"none\";return false;'>/$class/$function</a></h3>";

            print "<div class='parameters'  id='$class/$function' style='display:none'>";
            foreach ($parameters["params"] as $parameter => $properties) {
                print "<div class='parameter'>";

                if ($properties["required"])
                    print "<p><b>$parameter</b> *</p>";
                else
                    print "<p>$parameter</p>";

                print "</div>";
            }
            print "</div>";

        print "</div>";
    }

    print "</div>";
}
