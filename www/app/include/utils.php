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
        $expression = "/^(?P<path>[^\?]*)(\?(?P<args>.*))?$/u";

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

    public static function extractComponent($path, $offset, $default = "") {
        $pathComponents = Utils::pathToComponents($path);
        return isset($pathComponents[$offset]) ? $pathComponents[$offset] : $default;
    }

    public static function createAuthCookie($session, $keepLogged = false) {
        setcookie("web_session", $session, time() + ($keepLogged ? 60*60*24*365 : 60*60), "/", null, null, true);
        setcookie("expires", time() + ($keepLogged ? 60*60*24*365 : 60*60), time() + ($keepLogged ? 60*60*24*365 : 60*60), "/", null, null, true);
    }

    public static function getAuthCookie() {
        if (isset($_COOKIE["web_session"]))
            return $_COOKIE["web_session"];

        return null;
    }

    public static function destroyAuthCookie() {
        setcookie("web_session", "", 1, "/", null, null, true);
        setcookie("expires", "", 1, "/", null, null, true);
    }

    public static function updateAuthCookie() {
        $expires = (isset($_COOKIE["expires"]) ? $_COOKIE["expires"] : time()) - time();

        // update if expires soon
        if ($expires < 30 * 60) {
            $session = self::getAuthCookie();

            // only update if ok
            if ($session != null)
                self::createAuthCookie($session);
        }
    }

    public static function getMonths() {
        $months = array();
        for ($i = 1; $i <= 12; $i++)
            $months[$i] = date("F", strtotime("1.1.2000 + ".($i-1)." months"));

        return $months;
    }

    public static function getYears() {
        return array_reverse(range(1900, date("Y")));
    }

    public static function getDays() {
        return range(1,31);
    }

    public static function getGenders() {
        return array(
            "male" => "Man",
            "female" => "Woman"
        );
    }

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
}
