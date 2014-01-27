<?php

class Utils
{
    /**
     * Parse url
     * @param $request_uri
     * @param $path
     * @param $args
     * @return bool
     */
    public static function parseUrlPath($request_uri, &$path, &$args) {
        $expression = "/^(?P<path>[^\\?]*)(\\?(?P<args>.*))?$/u";

        if (preg_match($expression, $request_uri, $params)) {

            // get path component
            if (isset($params["path"])) {
                $path = urldecode($params["path"]);
            }

            // parse parameters
            if (isset($params["args"])) {
                parse_str($params["args"], $args);
            }

            return true;
        }

        return false;
    }

    public static function pathToComponents($path) {
        $path = trim($path, "/");
        return explode("/", $path);
    }

    public static function getRandom($length) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }

    public static function getBirthday($birthday) {
        if (preg_match("/^(\\d{1,2})\\.(\\d{1,2})\\.(\\d{4})$/", $birthday, $matches))
            return str_pad($matches[1], 2, "0", STR_PAD_LEFT) . str_pad($matches[2], 2, "0", STR_PAD_LEFT) . str_pad($matches[3], 4, "0", STR_PAD_LEFT);

        throw new Exception("Invalid birthday format");
    }

    public static function fromBirthday($birthdayRaw) {
        if (preg_match("/^(\\d{2})(\\d{2})(\\d{4})$/", $birthdayRaw, $matches))
            return array(
                "day" => ltrim($matches[1], "0"),
                "month" => ltrim($matches[2], "0"),
                "year" => ltrim($matches[3], "0"),
            );

        return null;
    }

    public static function getAge($birthday) {
        $bday = new DateTime($birthday["day"] . "." . $birthday["month"] . "." . $birthday["year"]);
        $today = new DateTime();

        $diff = $today->diff($bday);
        return $diff->y;
    }

    /**
     * Reverse city from geographic point
     * @param Database $database
     * @param $latitude
     * @param $longitude
     * @param int $distance
     * @return array
     */
    public static function reverseGeolocation(Database $database, $latitude, $longitude, $distance = 15) {
        $response = array(
            "city_id" => 0,
            "region_id" => 0,
            "country_id" => 0
        );

        $longitudeFrom = (float)$longitude - (float)$distance / abs(cos(deg2rad($latitude))*69.0);
        $longitudeTo = (float)$longitude + (float)$distance / abs(cos(deg2rad($latitude))*69.0);

        $latitudeFrom = (float)$latitude - ((float)$distance/69.0);
        $latitudeTo = (float)$latitude + ((float)$distance/69.0);

        $match = $database->fetch("SELECT
            id, country_code, region_code,
                3956 * 2 * ASIN(SQRT(POWER(SIN((:lookup_lat - (latitude)) *
                PI()/180 / 2),2) +
                COS(:lookup_lat * PI()/180) * COS((latitude) * PI()/180) *
                POWER(SIN((:lookup_long - longitude) * PI()/180 / 2),2))) as distance
            FROM location_cities
            WHERE
                latitude BETWEEN :lat_from AND :lat_to
                AND longitude BETWEEN :long_from AND :long_to
            ORDER BY distance ASC
            LIMIT 1",
            array(
                "lookup_lat" => $latitude,
                "lookup_long" => $longitude,
                "long_from" => $longitudeFrom,
                "long_to" => $longitudeTo,
                "lat_from" => $latitudeFrom,
                "lat_to" => $latitudeTo
            )
        );

        if ($match) {
            $response["city_id"] = (int)$match["id"];

            $response["country_id"] = (int)$database->fetchColumn("SELECT id FROM location_countries
                WHERE country_code = :country_code",
                array("country_code" => $match["country_code"]));

            $response["region_id"] = (int)$database->fetchColumn("SELECT id FROM location_regions
                WHERE country_code = :country_code AND region_code = :region_code",
                array("country_code" => $match["country_code"], "region_code" => $match["region_code"]));
        }

        return $response;
    }

    public static function geoipGetRecord($ip) {
        if (!function_exists("geoip_record_by_name")) {
            // geoip does not works
            // TODO - we will use internal DB later...
            $record = json_decode(file_get_contents("http://freegeoip.net/json/" . urlencode($ip)), true);

            // return region_name as region
            $record["region"] = $record["region_name"];
            return $record;
        }

        return geoip_record_by_name($ip);
    }

    public static function resolveLocation(Database $database, $countryCode, $regionCode, $cityName) {
        $response = array(
            "city_id" => 0,
            "region_id" => 0,
            "country_id" => 0
        );

        $response["country_id"] = (int)$database->fetchColumn("SELECT id FROM location_countries
                WHERE country_code = :country_code",
            array("country_code" => $countryCode));

        $response["region_id"] = (int)$database->fetchColumn("SELECT id FROM location_regions
                WHERE country_code = :country_code AND region_code = :region_code",
            array("country_code" => $countryCode, "region_code" => $regionCode));

        $response["city_id"] = (int)$database->fetchColumn("SELECT id FROM location_cities
                WHERE country_code = :country_code AND region_code = :region_code AND name = :name",
            array("country_code" => $countryCode, "region_code" => $regionCode, "name" => $cityName));

        // TODO log missing cities?
        if (!$response["city_id"]) {
            // try to lookup without region
            $matchedCities = $database->fetchAll("SELECT id FROM location_cities
                WHERE country_code = :country_code AND name = :name LIMIT 2",
                array("country_code" => $countryCode, "name" => $cityName));

            // only set if there is one match of the city
            if (count($matchedCities) == 1) {
                $response["city_id"] = (int)$matchedCities[0]["id"];
            }
        }

        return $response;
    }

    public static function translateLocation(Database $database, &$array) {
        $array["location"] = array();

        if (array_key_exists("location_longitude", $array) && array_key_exists("location_latitude", $array)) {
            if (!empty($array["location_latitude"]) && !empty($array["location_longitude"])) {
                $array["location"]["latitude"] = $array["location_latitude"];
                $array["location"]["longitude"] = $array["location_longitude"];
            }

            unset($array["location_longitude"]);
            unset($array["location_latitude"]);
        }

        if (array_key_exists("location_title", $array)) {
            if (!empty($array["location_title"])) {
                $array["location"]["title"] = $array["location_title"];
            }

            unset($array["location_title"]);
        }

        if (array_key_exists("location_city", $array) && array_key_exists("location_region", $array) && array_key_exists("location_country", $array)) {
            if (!empty($array["location_city"])) {
                $array["location"]["city"]["id"] = $array["location_city"];
                $array["location"]["city"]["name"] = $database->fetchColumn("SELECT name FROM location_cities WHERE id = :city_id",
                    array("city_id" => $array["location_city"]));
            }

            if (!empty($array["location_region"])) {
                $array["location"]["region"]["id"] = $array["location_region"];
                $array["location"]["region"]["name"] = $database->fetchColumn("SELECT name FROM location_regions WHERE id = :region_id",
                    array("region_id" => $array["location_region"]));
            }

            if (!empty($array["location_country"])) {
                $array["location"]["country"]["id"] = $array["location_country"];
                $array["location"]["country"]["name"] = $database->fetchColumn("SELECT name FROM location_countries WHERE id = :country_id",
                    array("country_id" => $array["location_country"]));
            }

            unset($array["location_city"]);
            unset($array["location_region"]);
            unset($array["location_country"]);
        }
    }

    public static function saveImage($sourceFilename, $savePath, $extra = 'null') {
        $fileName = Utils::getRandom(2) . "_" . $extra . "_" . Utils::getRandom(20) . ".jpg";

        // Set a maximum height and width
        $width = 1024;
        $height = 1024;

        // Get new dimensions
        list($width_orig, $height_orig, $type) = getimagesize($sourceFilename);

        if ($width_orig < $width && $height_orig < $height) {
            $width = $width_orig;
            $height = $height_orig;
        }
        else {
            $ratio_orig = $width_orig / $height_orig;

            if ($width / $height > $ratio_orig) {
                $width = $height*$ratio_orig;
            } else {
                $height = $width / $ratio_orig;
            }
        }

        // Resample
        $image_p = imagecreatetruecolor($width, $height);

        if ($type == IMAGETYPE_GIF)
            $image = imagecreatefromgif($sourceFilename);
        else if ($type == IMAGETYPE_PNG)
            $image = imagecreatefrompng($sourceFilename);
        else
            $image = imagecreatefromjpeg($sourceFilename);

        if (!$image)
            throw new Exception("Source image is invalid");

        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

        // Output
        imagejpeg($image_p, $savePath . $fileName, 90);
        return $fileName;
    }

    public static function deleteImage($savePath, $filename) {
        unlink($savePath . $filename);
    }

    public static function updateImageUrl($image, $url) {
        if (!empty($image))
            return $url . $image;
        return "";
    }

    /**
     * Format time display
     * @param $time
     * @param $format
     * @return bool|string
     */
    public static function updateTime($time, $format) {
        if (empty($time))
            return "";

        if ($format == "ago")
            return self::timeAgo($time);
        if ($format == "day")
            return date("m/d/Y", $time);

        return $time;
    }

    /**
     * Unpack feature list from packed number
     * @param $packedData int
     * @param $features array associative array with supported features (keys) and their numeric codes (values)
     * @return array
     */
    public static function unpackData($packedData, $features) {
        $list = array();

        foreach ($features as $featureCode => $name) {
            if ((int)$packedData & (int)$featureCode)
                array_push($list, $name);
        }

        return $list;
    }

    /**
     * Created packed number from feature list
     * @param $unpackedData array|string array or json array containing list of features
     * @param $features array associative array with supported features (keys) and their numeric codes (values)
     * @return int
     */
    public static function packData($unpackedData, $features) {
        $packedData = 0;

        if (!is_array($unpackedData))
            $unpackedData = json_decode($unpackedData, false);

        if (!is_array($unpackedData))
            return $packedData;

        foreach ($unpackedData as $name) {
            $featureCode = array_search($name, $features);
            $packedData |= (int)$featureCode;
        }

        return $packedData;
    }

    /**
     * Bitwise check for feature in packed number
     * @param $packedData
     * @param $featureCode
     * @return bool
     */
    public static function hasFeature($packedData, $featureCode) {
        if ((int)$packedData & (int)$featureCode)
            return true;

        return false;
    }

    /**
     * Calculate distance between two geographic points
     * @param $from array containing latitude and longitude
     * @param $to array containing latitude and longitude
     * @return float|int
     */
    public static function calculateDistance($from, $to) {
        if (!isset($from["latitude"]) || !isset($from["longitude"]) ||
            !isset($to["latitude"]) || !isset($to["longitude"]))
            return 0;

        $lat1 = (float)$from["latitude"];
        $lon1 = (float)$from["longitude"];

        $lat2 = (float)$to["latitude"];
        $lon2 = (float)$to["longitude"];

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        return $miles;
    }

    /**
     * Get ago formatted string from timestamp
     * @param $timestamp
     * @return bool|string
     */
    public static function timeAgo($timestamp) {
        if (!is_numeric($timestamp)) {
            // It's not a time stamp, so try to convert it...
            $timestamp = strtotime($timestamp);
        }

        if (!is_numeric($timestamp)) {
            // If its still not numeric, the format is not valid
            return false;
        }

        // Calculate the difference in seconds
        $difference = time() - $timestamp;

        if ($difference < 3) {
            return "Just now";
        }
        if ($difference < 60) {
            return $difference . " seconds ago";
        }
        if ($difference < (60*2)) {
            return "1 minute ago";
        }
        if ($difference < (60*60)) {
            return intval($difference / 60) . " minutes ago";
        }
        if ($difference < (60*60*2)) {
            return "1 hour ago";
        }
        if ($difference < (60*60*24)) {
            return intval($difference / (60*60)) . " hours ago";
        }
        if ($difference < (60*60*24*2)) {
            return "1 day ago";
        }
        if ($difference < (60*60*24*7)) {
            return intval($difference / (60*60*24)) . " days ago";
        }
        if ($difference < (60*60*24*7*2)) {
            return "1 week ago";
        }
        if ($difference < (60*60*24*7*(52/12))) {
            return intval($difference / (60*60*24*7)) . " weeks ago";
        }
        if ($difference < (60*60*24*7*(52/12)*2)) {
            return "1 month ago";
        }

        return intval($difference / (60*60*24*7*(52/12))) . " months ago";
    }

    /**
     * Clean up input html from potential malicious tags and attributes
     * @param $html
     * @return string
     */
    public static function sanitizeHtml($html) {
        if (strlen(trim($html)) == 0)
            return "";

        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $dom->encoding = "UTF-8";
        // hack to load file as utf-8
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);

        // remove hack <?xml ...
        foreach ($dom->childNodes as $item)
            if ($item->nodeType == XML_PI_NODE)
                $dom->removeChild($item);

        $nodes = $dom->getElementsByTagName('*');
        $removeNode = array();

        $allowedTags = array(
            "a", "img", "b", "strong",
            "h1", "h2", "h3", "h4", "h5", "h6",
            "i", "em", "br", "ul", "ol", "li", "p", "blockquote", "s",
            "table", "tbody", "thead", "tfooter", "caption", "tr", "th", "td",
            "iframe"
        );

        $allowedParameters = array("href", "title", "alt", "target", "src", "width", "height", "frameborder");

        // iterate over all tags
        foreach ($nodes as $node) {
            $tagName = $node->nodeName;
            if (in_array($tagName, $allowedTags)) {
                // iterate over attributes if any
                if (!is_null($node->attributes)) {
                    $removeAttributes = array();

                    foreach ($node->attributes as $attribute) {
                        $attributeName = $attribute->name;
                        if (in_array($attributeName, $allowedParameters)) {
                            // get rid of src and href attributes that could contain malicious javascript: etc.
                            if ($attributeName == "src" || $attributeName == "href") {
                                if (!self::isLink($attribute->value))
                                    array_unshift($removeAttributes, $attribute);
                            }
                        }
                        else {
                            // add to list of attributes we want to remove
                            array_unshift($removeAttributes, $attribute);
                        }
                    }

                    // remove attributes
                    foreach ($removeAttributes as $attribute) {
                        $node->removeAttributeNode($attribute);
                    }
                }
            }
            else {
                // add to list of tags we want to remove
                array_unshift($removeNode, $node);
            }
        }

        // remove tags we do not need - but keep their contents
        foreach ($removeNode as $from) {
            $sibling = $from->firstChild;
            if ($sibling) {
                do {
                    $next = $sibling->nextSibling;
                    $from->parentNode->insertBefore($sibling, $from);
                } while ($sibling = $next);
            }
            $from->parentNode->removeChild($from);
        }

        // remove <!DOCTYPE
        $dom->removeChild($dom->firstChild);
        return $dom->saveHTML();
    }

    /**
     * Simple check for valid link
     * @param $link
     * @return bool
     */
    public static function isLink($link) {
        if (!preg_match("/^(https?:)?\\/\\//i", $link))
            return false;
        return true;
    }

    /**
     * Check for HTTPS connection
     * @return bool
     */
    public static function isConnectionSecure() {
        return isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on";
    }
}