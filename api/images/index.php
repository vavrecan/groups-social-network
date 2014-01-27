<?php

function exception_handler($exception) {
	header("HTTP/1.1 500", true, "500");
	echo "Unable to get image: " , $exception->getMessage(), "\n";
    exit;
}

function error_handler($code, $message, $file, $line) {
    throw new ErrorException($message, 0, $code, $file, $line);
}

function not_found() {
    header("HTTP/1.1 404", true, "404");
    echo "<h1>Not Found</h1>";
    exit;
}

//set_error_handler("error_handler");
set_exception_handler("exception_handler");
$config = include("../config.php");

$url = $_SERVER["REQUEST_URI"];

// remove base from url
$base = rtrim($config["base"], "/");
$url = substr($url, strlen($base));

$components = explode("/", trim($url, "/"));
if (count($components) < 3)
    throw new Exception("Invalid amount of arguments");

// get info from components
$size = $components[1];
$file = $components[2];

$supported = array(
    "p50x50" => array("width" => 50, "height" => 50),
    "p150x150" => array("width" => 150, "height" => 150),
    "p300x300" => array("width" => 300, "height" => 300)
);

if (!array_key_exists($size, $supported))
    throw new Exception("Unsupported size format");

$cacheOn = true;
$originalFolder = __DIR__ . "/org/";
$resizedFolder = __DIR__ . "/{$size}/";

$sourceFile = "{$originalFolder}/{$file}";
$cacheFile = "{$resizedFolder}/{$file}";

if (!file_exists($sourceFile))
    not_found();

if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($sourceFile) && $cacheOn)
	send_image_file($cacheFile);

$image = getimagesize($sourceFile);
if ($image === false)
	throw new Exception("Unable to read source image");

$size_w = $supported[$size]["width"];
$size_h = $supported[$size]["height"];

$orig_w = $image[0];
$orig_h = $image[1];

$w_ratio = ($size_w / $orig_w);
$h_ratio = ($size_h / $orig_h);

if ($orig_w > $orig_h ) {//landscape
    $crop_w = round($orig_w * $h_ratio);
    $crop_h = $size_h; 
    $src_x = ceil( ( $orig_w - $orig_h ) / 2 );
    $src_y = 0;
} elseif ($orig_w < $orig_h ) {//portrait
    $crop_h = round($orig_h * $w_ratio);
    $crop_w = $size_w;
    $src_x = 0;
    $src_y = ceil( ( $orig_h - $orig_w ) / 2 );
} else {//square
    $crop_w = $size_w;
    $crop_h = $size_h;
    $src_x = 0;
    $src_y = 0;
}

@ini_set('memory_limit', '40M');

$supported_types = array(
	IMAGETYPE_GIF => array('get' => 'imagecreatefromgif', 'put' => 'imagegif'),
	IMAGETYPE_JPEG => array('get' => 'imagecreatefromjpeg', 'put' => 'imagejpeg'),
	IMAGETYPE_PNG => array('get' => 'imagecreatefrompng', 'put' => 'imagepng'),
	IMAGETYPE_WBMP => array('get' => 'imagecreatefromwbmp', 'put' => 'imagewbmp'),
	IMAGETYPE_XBM => array('get' => 'imagecreatefromxbm', 'put' => 'imagexbm'),
);

if (defined('IMAGETYPE_XPM'))
	$supported_types[IMAGETYPE_XPM] = array('get' => 'imagecreatefromxpm', 'put' => 'imagexpm');

if (isset($supported_types[$image[2]]) && function_exists($supported_types[$image[2]]['get']))
	$gd_funcs = $supported_types[$image[2]];
else
	throw new Exception("Image format is unsupported");

$source = call_user_func($gd_funcs['get'], $sourceFile);
$thumb = imagecreatetruecolor($size_w, $size_h);

if (function_exists('imagecopyresampled'))
	imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $crop_w, $crop_h, $orig_w, $orig_h);
else
	imagecopyresized($thumb, $source, 0, 0, $src_x, $src_y, $crop_w, $crop_h, $orig_w, $orig_h);

$handler = @fopen($cacheFile, 'a+');
@fclose($handler);
@chmod($cacheFile, 0777);
	
imagedestroy($source);
call_user_func($gd_funcs['put'], $thumb, $cacheFile);
imagedestroy($thumb);

if (!file_exists($cacheFile))
	throw new Exception("Unable to resize image file");

send_image_file($cacheFile);

function send_image_file($file)
{
    $mtime = filemtime($file);

	header('Content-Type: image/jpeg');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	
	$etag = '"' . md5_file($file) . '"';
	$not_modified = false;

	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		$time = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		$not_modified = strtotime($time[0]) >= $mtime;
	}
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && !$not_modified)
    {
		$not_modified = strpos($_SERVER['HTTP_IF_NONE_MATCH'], $etag) !== false;
    }

	if ($not_modified)
	{
		ob_end_clean();

		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	header('ETag: ' . $etag);
	header('Content-Length: ' . filesize($file));

	readfile($file);
    exit;
}
