<?php

require("utils.php");
require("api.php");

require("action.php");
require("actions/activity.php");
require("actions/auth.php");
require("actions/profile.php");
require("actions/group.php");
require("actions/groups.php");
require("actions/user.php");
require("actions/events.php");
require("actions/notifications.php");
require("actions/messages.php");
require("actions/search.php");

class Site
{
    public $requestMethod;
    public $requestPath;
    /**
     * first path component in request path /<action>-<id>/other
     */
    public $requestPathAction;
    public $requestPathId;

    public $requestParams;

    public $config;
    public $response;

    /**
     * Enable JSON response
     * @var bool
     */
    public $json;

    /**
     * Is template outputed?
     * @var bool
     */
    private $outputed;

    /**
     * @var API class
     */
    public $api;

    /**
     * Info about user obtained from get user info
     * id, username, profile_photo
     * @var array
     */
    public $user;

    /**
     * set if user was initialized
     * @var bool
     */
    private $userInitialized;

    public function __construct($config) {
        $this->json = false;
        $this->outputed = false;
        $this->userInitialized = false;

        set_error_handler(array($this, "onError"));
        set_exception_handler(array($this, "onException"));
        register_shutdown_function(array($this, "onShutdown"));

        $this->response = array();
        $this->config = $config;
        $this->setHttpRequest();

        $this->api = new API($this->config["api_url"]);
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

        $pathAction = isset($pathComponents[0]) ? $pathComponents[0] : "";
        $pathId = null;

        if (preg_match("/^(?P<path>.*)\\-(?P<id>\\d+)$/", $pathAction, $match)) {
            $pathAction = $match["path"];
            $pathId = $match["id"];
        }

        $this->requestPathId = $pathId;
        $this->requestPathAction = $pathAction;
    }

    public function run() {
        if ($this->config["maintenance"]) {
            header("HTTP/1.1 503", true, "503");
            die("Site is under maintenance");
        }

        $staticPages = array(
            "contact" => "static/contact",
            "terms" => "static/terms",
            "confirm-email" => "static/confirm-email",
        );

        if (array_key_exists($this->requestPathAction, $staticPages)) {
            $this->outputTemplate($staticPages[$this->requestPathAction]);
            return;
        }

        $this->initializeSession();

        if ($this->requestPathAction == "") {
            if ($this->isLogged()) {
                $this->requestPath = "/groups/list";
                $this->requestPathAction = "groups";
            }
            else {
                $this->response["months"] = Utils::getMonths();
                $this->response["days"] = Utils::getDays();
                $this->response["years"] = Utils::getYears();
                $this->response["genders"] = Utils::getGenders();
                $this->outputTemplate("register");
                return;
            }
        }

        // api handler
        if ($this->requestPathAction == "api") {
            $this->json = true;
            $path = substr($this->requestPath, strlen("/api"));

            if (isset($_FILES["image"]))
                $response = $this->api->callUpload($path, $this->requestParams, "image", $_FILES['image']['tmp_name']);
            else
                $response = $this->api->call($path, $this->requestParams);

            $this->response = $response;
            $this->outputTemplate("message");
            return;
        }

        // handle using other handler
        $loginPages = array(
            "activity" => "Activity",
            "profile" => "Profile",
            "groups" => "Groups",
            "auth" => "Auth",
            "group" => "Group",
            "user" => "User",
            "events" => "Events",
            "notifications" => "Notifications",
            "messages" => "Messages",
            "search" => "Search"
        );

        if (isset($loginPages[$this->requestPathAction])) {
            $className = $loginPages[$this->requestPathAction];
            $action = new $className($this);
            $action->run();
        }
        else {
            $this->notFound();
        }
    }

    public function outputTemplate($template) {
        if ($this->outputed) {
            // output directly on error
            if (!empty($this->response))
                print_r($this->response);

            return;
        }

        if ($this->json) {
            // use text json so IE wont download it
            header("Content-type: text/json; charset=UTF-8");
            print json_encode($this->response);
        }
        else {
            require_once(LIBS_DIR . "/Smarty/libs/Smarty.class.php");

            header("X-Frame-Options: SAMEORIGIN");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Pragma: no-cache");

            header("Content-type: text/html; charset=UTF-8");

            $smarty = new \Smarty();

            $smarty->setTemplateDir(TEMPLATES_DIR);
            $smarty->setCompileDir(TEMP_DIR);
            $smarty->muteExpectedErrors();

            // assign data
            $smarty->assign("json_options", JSON_HEX_AMP|JSON_HEX_TAG);
            $smarty->assign("base_assets", $this->config["base_assets"]);
            $smarty->assign("base", $this->config["base"]);
            $smarty->assign("site_url", $this->config["site_url"]);
            $smarty->assign("api_url", $this->config["api_url"]);

            $smarty->assign($this->response);

            // assign current logged user
            $smarty->assign("user", $this->getLoggedUser());

            // current path action
            $smarty->assign("path_action", $this->requestPathAction);

            $this->outputed = true;
            $smarty->display($template . ".tpl");
        }
    }

    public function returnTemplate($template) {
        require_once(LIBS_DIR . "/Smarty/libs/Smarty.class.php");

        $smarty = new \Smarty();

        $smarty->setTemplateDir(TEMPLATES_DIR);
        $smarty->setCompileDir(TEMP_DIR);
        $smarty->muteExpectedErrors();

        // assign data
        $smarty->assign($this->config);
        $smarty->assign($this->response);

        // assign current logged user
        $smarty->assign("user", $this->getLoggedUser());
        $smarty->assign("path_action", $this->requestPathAction);

        return $smarty->fetch($template . ".tpl");
    }

    public function redirect($path = "") {
        $redirectPath = $this->config["base"] . $path;

        if ($this->json) {
            header("Content-type: application/json; charset=UTF-8");
            print json_encode(array("redirect" => $redirectPath));
        }
        else {
            header("Location: " . $redirectPath);
        }
    }

    public function onError($errno, $errstr, $errfile, $errline, $errcontext) {
        // TODO log error to the DB

        // only show error if development environment
        if (isset($this->config["environment"]) && $this->config["environment"] == "development") {
            $this->response = array(
                "error" => array(
                    "message" => $errstr,
                    "file" => $errfile,
                    "line" => $errline
                )
            );
        }
        else {
            $this->response = array(
                "message" => $errstr,
            );
        }

        if (!$this->json)
            header("HTTP/1.1 500", true, "500");
        else
            header("HTTP/1.1 200", true, "200");

        $this->outputTemplate("errors/error");
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

    public function notFound() {
        header("HTTP/1.1 404", true, "404");
        $this->outputTemplate("errors/not-found");
    }

    public function requireLogin() {
        if (!$this->isLogged()) {
            throw new Exception("Not logged in");
        }
    }

    private function initializeSession() {
        $this->userInitialized = true;
        $webSession = Utils::getAuthCookie();

        if ($webSession == null)
            return;

        $response = $this->api->login($webSession);

        if (isset($response["error"])) {
            Utils::destroyAuthCookie();
            throw new Exception($response["error"]["message"]);
            return;
        }

        Utils::updateAuthCookie();
        $this->user = $response;
    }

    private function isLogged() {
        if (!$this->userInitialized)
            $this->initializeSession();

        return $this->user != null;
    }

    private function getLoggedUser() {
        if (!$this->isLogged())
            return null;

        return $this->user;
    }
}