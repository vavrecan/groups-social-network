<?php

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");
error_reporting(E_ALL);
ini_set("display_errors", 1);

$config = include("../../config.php");
include("../../include/database.php");
$imagesDirectory = "../../images";

$database = new Database($config["database"]["dns"], $config["database"]["username"], $config["database"]["password"]);
$database->connect();

$usedImages = array();
$usedImages = array_merge($usedImages, $database->fetchAllColumn("SELECT image FROM feed_images INNER JOIN feed ON feed.feed_image_id = feed_images.id WHERE 1 = 1")); // feed.active
$usedImages = array_merge($usedImages, $database->fetchAllColumn("SELECT image FROM gallery_images INNER JOIN gallery ON gallery.id = gallery_images.gallery_id WHERE 1 = 1")); // gallery.active
$usedImages = array_merge($usedImages, $database->fetchAllColumn("SELECT image FROM groups WHERE 1 = 1 AND groups.image != ''")); // groups.active
$usedImages = array_merge($usedImages, $database->fetchAllColumn("SELECT image FROM users WHERE 1 = 1 AND users.image != ''")); // users.active

foreach (glob($imagesDirectory . "/*") as $file) {
    // check for sub folders - p50x50 and so on
    if (is_dir($file)) {

        // look through all images
        foreach(glob($file . "/*") as $imagePath) {
            if (is_file($imagePath)) {
                $image = basename($imagePath);
                if (!in_array($image, $usedImages))
                    unlink($imagePath);
            }
        }

    }
}

//var_dump($usedImages);
