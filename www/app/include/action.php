<?php

abstract class Action {
    protected $requireLogin = false;

    /**
     * Parent site
     * @var Site
     */
    protected $site;

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $pathAction;

    /**
     * @var int
     */
    public $pathId;

    /**
     * @var array
     */
    public $params;

    /**
     * @var string
     */
    public $method;

    /**
     * @var array
     */
    public $response;

    /**
     * @var API
     */
    public $api;

    /**
     * @var array
     */
    public $config;

    /**
     * @var array
     */
    public $user;

    public function __construct(Site $site) {
        $this->site = $site;
        $this->params = &$site->requestParams;
        $this->response = &$site->response;
        $this->method = &$site->requestMethod;
        $this->pathAction = &$site->requestPathAction;
        $this->pathId = &$site->requestPathId;
        $this->path = &$site->requestPath;
        $this->api = &$site->api;
        $this->config = &$site->config;
        $this->user = &$site->user;

        if ($this->requireLogin)
            $this->requireLogin();
    }

    protected function requireParams($params) {
        if (!is_array($params)) {
            $params = array($params);
        }

        foreach ($params as $param)
            if (!isset($this->params[$param]))
                throw new Exception("Missing required parameter: {$param}");
    }

    public function requireMethod($method) {
        if ($method != $this->method)
            throw new Exception("Expected method $method");
    }

    public function requireLogin() {
        return $this->site->requireLogin();
    }

    public function redirect($path = "") {
        return $this->site->redirect($path);

    }

    public function output($template) {
        return $this->site->outputTemplate($template);
    }

    public function enableJson() {
        if (isset($this->params["format"]) && $this->params["format"] == "json")
            $this->site->json = true;
    }

    public function returnTemplate($template) {
        return $this->site->returnTemplate($template);
    }

    public abstract function run();
}

class ActionHandler extends Action
{
    protected $paramOffset = 1;

    public function run() {
        $action = Utils::extractComponent($this->path, $this->paramOffset, "index");
        $actionId = 0;
        if (preg_match("/(?P<action>\\d+)$/", $action, $matches))
            $actionId = $matches["action"];

        $action = preg_replace("/(\\d+)$/", "", $action);
        $method = str_replace("-", "", $action) . "Handler";

        if (is_callable(array($this, $method)))
            call_user_func(array($this, $method), $actionId);
        else
            $this->site->notFound();
    }
}