<?php

/**
 * Used to update geonames for reverse geocoding
 * lat, log to country, region and city name
 */

// this must be present
if (!isset($_SERVER["SERVER_NAME"]))
    $_SERVER["SERVER_NAME"] = "localhost";

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
set_error_handler("exception_error_handler");
error_reporting(E_ALL);
ini_set("display_errors", 1);

if (!file_exists("data/countryInfo.txt")) {
    file_put_contents("data/countryInfo.txt", file_get_contents("http://download.geonames.org/export/dump/countryInfo.txt"));
}

if (!file_exists("data/admin1CodesASCII.txt")) {
    file_put_contents("data/admin1CodesASCII.txt", file_get_contents("http://download.geonames.org/export/dump/admin1CodesASCII.txt"));
}

if (!file_exists("data/cities1000.txt")) {
    file_put_contents("data/cities1000.zip", file_get_contents("http://download.geonames.org/export/dump/cities1000.zip"));

    $zip = new ZipArchive;
    if ($zip->open('data/cities1000.zip') === TRUE) {
        $zip->extractTo('data/', array('cities1000.txt'));
        $zip->close();

        unlink('data/cities1000.zip');
    } else {
        echo 'failed';
    }
}

function get_country_map() {
    $map = array();

    $data = file_get_contents("data/countryInfo.txt");
    $lines = explode("\n", $data);

    foreach ($lines as $line) {
        if (strlen($line) == 0 || $line{0} == "#") continue;

        $columns = explode("\t", $line);
        $key = $columns[0];
        $value = $columns[4];

        $map[$key] = $value;
    }

    return $map;
}

// use to lookup region or use geoip_region_name_by_code($ipData['country_code'], $ipData["region"]) ... SK . ID
function get_region_map() {
    $map = array();

    $data = file_get_contents("data/admin1CodesASCII.txt");
    $lines = explode("\n", $data);

    foreach ($lines as $line) {
        if (strlen($line) == 0 || $line{0} == "#") continue;

        $columns = explode("\t", $line);
        $key = $columns[0];
        $value = $columns[1];

        $map[$key] = $value;
    }

    return $map;
}

$config = include("../../config.php");
include("../../include/database.php");

$database = new Database($config["database"]["dns"], $config["database"]["username"], $config["database"]["password"]);
$database->connect();

$countries = get_country_map();
$regions = get_region_map();

$data = file_get_contents("data/cities1000.txt");
$lines = explode("\n", $data);

// update countries
foreach ($countries as $country_code => $country) {
    $database->exec("INSERT INTO location_countries(country_code, name) VALUES
            (:country_code, :country) ON DUPLICATE KEY UPDATE
                name = :country",
        array(
            "country_code" => $country_code,
            "country" => $country,
        )
    );
}

// update regions
foreach ($regions as $data => $region) {
    list($country_code, $region_code) = explode(".", $data, 2);
    $database->exec("INSERT INTO location_regions(region_code, country_code, name) VALUES
            (:region_code, :country_code, :name) ON DUPLICATE KEY UPDATE
                name = :name, country_code = :country_code",
        array(
            "country_code" => $country_code,
            "region_code" => $region_code,
            "name" => $region,
        )
    );
}

// update cities
try {
    foreach ($lines as $line) {
        $columns = explode("\t", $line);
        if (count($columns) < 2) continue;

        $geonameid = $columns[0];
        $city_name = $columns[1];
        $latitude = $columns[4];
        $longitude = $columns[5];
        $country_code = $columns[8];
        $region_code = $columns[10];
        $population = $columns[14];

        if (!array_key_exists($country_code, $countries))
            throw new Exception("Invalid country on: #". $line);

        $country_name = $countries[$country_code];
        $region_name = false;

        // lookup region from region map
        if (!array_key_exists("{$country_code}.{$region_code}", $regions)) {
            $region_name = "";
        }
        else {
            $region_name = $regions["{$country_code}.{$region_code}"];
        }

        $database->exec("INSERT INTO location_cities(geoname_id, country_code, region_code, name, latitude, longitude) VALUES
            (:geonameid, :country_code, :region_code, :city, :latitude, :longitude) ON DUPLICATE KEY UPDATE
                name = :city, country_code = :country_code,  region_code = :region_code,  latitude = :latitude, longitude = :longitude",
            array(
                "geonameid" => $geonameid,
                "country_code" => $country_code,
                "region_code" => $region_code,
                "city" => $city_name,
                "latitude" => $latitude,
                "longitude" => $longitude,
            )
        );
    }
}
catch (Exception $e) {
    var_dump($e); exit;
}