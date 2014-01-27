<?php

require_once("utils.php");
require_once("method.php");
require_once("database.php");

require_once("methods/activity.php");
require_once("methods/auth.php");
require_once("methods/user.php");
require_once("methods/group.php");
require_once("methods/article.php");
require_once("methods/event.php");
require_once("methods/gallery.php");
require_once("methods/comment.php");
require_once("methods/voting.php");
require_once("methods/feed.php");
require_once("methods/notification.php");
require_once("methods/location.php");
require_once("methods/message.php");
require_once("methods/moderation.php");

class Api
{
    public $requestMethod;
    public $requestPath;

    public $database;

    /**
     * first path component in request path /<action>/other
     */
    public $requestPathAction;
    public $requestParams;

    public $config;
    public $response;

    private $outputed = false;

    public function __construct($config) {
        // error handle all except deprecated errors
        set_error_handler(array($this, "onError"), E_ALL ^ E_DEPRECATED);
        set_exception_handler(array($this, "onException"));
        register_shutdown_function(array($this, "onShutdown"));

        ignore_user_abort(true);

        $this->database = new Database($config["database"]["dns"], $config["database"]["username"], $config["database"]["password"]);
        $this->database->connect();

        $this->response = array();
        $this->config = $config;
        $this->setHttpRequest();
    }

    public function run() {
        if ($this->config["maintenance"]) {
            header("HTTP/1.1 503", true, "503");
            die("Site is under maintenance");
        }

        // replace default image format if passed as parameter
        if (isset($this->requestParams["image_format"])) {
            $imageFormat = $this->requestParams["image_format"];

            if (isset($this->config["images_formats"][$imageFormat]))
                $this->config["images_url"] = $this->config["images_formats"][$imageFormat];

            if ($imageFormat == "empty")
                $this->config["images_url"] = "";
        }

        // replace default time format if passed as parameter
        if (isset($this->requestParams["time_format"])) {
            $timeFormat = $this->requestParams["time_format"];
            $this->config["time_format"] = $timeFormat;
        }

        // decide what module to pick from first path action
        $modules = array(
            "activity" => "Activity",
            "article" => "Article",
            "auth" => "Auth",
            "comment" => "Comment",
            "event" => "Event",
            "feed" => "Feed",
            "gallery" => "Gallery",
            "group" => "Group",
            "location" => "Location",
            "message" => "Message",
            "moderation" => "Moderation",
            "notification" => "Notification",
            "user" => "User",
            "voting" => "Voting",
        );

        if (isset($modules[$this->requestPathAction])) {
            $className = $modules[$this->requestPathAction];
            $action = new $className($this);
            $action->run();
        }
        else {
            throw new Exception("Unhandled request");
        }
    }

    public function output() {
        if ($this->outputed) {
            print "/*";
        }
        else {
            header("X-Frame-Options: DENY");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Pragma: no-cache");
            header("Content-type: application/json; charset=UTF-8");
        }

        if (isset($this->config["environment"]) && $this->config["environment"] == "development" && isset($this->requestParams["pretty"])) {
            var_dump($this->response);
        }
        else {
            print json_encode($this->response);
        }

        if ($this->outputed) {
            print "*/";
        }

        $this->outputed = true;
    }

    public function redirect($path = "") {
        $redirectPath = $this->config["base"] . $path;
        header("Location: " . $redirectPath);
    }

    public function onError($errno, $errstr, $errfile, $errline, $errcontext) {
        // TODO log error to the DB

        if ($this->database->isConnected()) {
            //
        }

        // only show error if development environment
        if (isset($this->config["environment"]) && $this->config["environment"] == "development") {
            $this->response = array(
                "error" => array(
                    "message" => $errstr,
                    "code" => $errno,
                    "type" => get_class($errcontext),
                    "file" => $errfile,
                    "line" => $errline,
                    "trace" => $errcontext instanceof \Exception ? $errcontext->getTraceAsString() : ""
                )
            );
        }
        else {
            $this->response = array(
                "error" => array(
                    "message" => $errstr,
                    "code" => $errno,
                    "type" => get_class($errcontext)
                )
            );
        }

        header("HTTP/1.1 500", true, "500");
        $this->output();
    }

    public function onException(\Exception $exception) {
        $this->onError($exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception);
    }

    public function onShutdown() {
        // handle fatal errors
        $error = error_get_last();
        if ($error !== NULL) {
            $this->onError($error["type"], $error["message"], $error["file"], $error["line"], null);
        }
    }

    private function setHttpRequest() {
        $this->requestMethod = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : "GET";

        $params = array();
        $path = "/";

        // parse request uri into path and params
        $requestUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        Utils::parseUrlPath($requestUri, $path, $params);

        $this->requestPath = $path;
        $this->requestParams = $params;

        // add post params
        if (isset($_POST) && is_array($_POST) && count($_POST) > 0) {
            $getParams = $this->requestParams;
            // post can be consuming, just past as reference
            $this->requestParams = &$_POST;
            $this->requestParams += $getParams;
        }

        // remove base from request path
        $base = rtrim($this->config["base"], "/");
        $this->requestPath = substr($this->requestPath, strlen($base));

        $pathComponents = Utils::pathToComponents($this->requestPath);
        $this->requestPathAction = isset($pathComponents[0]) ? $pathComponents[0] : "";
    }
}

