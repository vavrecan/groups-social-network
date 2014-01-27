<?php

class Auth extends ActionHandler {
    public function loginHandler() {
        $this->enableJson();
        $this->requireMethod("POST");
        $this->requireParams(array("email", "password"));

        $keepLogged = isset($this->params["keep-logged-in"]) && $this->params["keep-logged-in"] == 1;

        $login = $this->api->call("/auth/login", array(
            "email" => $this->params["email"],
            "password" => $this->params["password"]
        ));

        // save web session
        if (!isset($login["session"]))
            throw new Exception("Missing session");

        $session = $login["session"];
        Utils::createAuthCookie($session, $keepLogged);
        $this->redirect();
    }

    public function registerHandler() {
        $this->enableJson();
        $this->requireMethod("POST");
        $this->requireParams(array("first_name","last_name", "email", "password", "password_again"));
        $this->requireParams(array("day", "month", "year", "gender"));

        if ($this->params["password"] != $this->params["password_again"])
            throw new Exception("Passwords do not match");

        $this->api->call("/auth/register", array(
            "last_name" => $this->params["last_name"],
            "first_name" => $this->params["first_name"],
            "gender" => $this->params["gender"],
            "email" => $this->params["email"],
            "password" => $this->params["password"],
            "birthday" => $this->params["day"] . "." . $this->params["month"] . "." . $this->params["year"],
            "verify_url" => "http://{$_SERVER['HTTP_HOST']}{$this->config['base']}auth/verify"
        ));
        $this->redirect("confirm-email");
    }

    public function forgottenPasswordHandler() {
        $this->enableJson();
        $this->requireMethod("POST");
        $this->requireParams(array("email"));

        $forgottenPassword = $this->api->call("/auth/forgottenPassword", array(
            "email" => $this->params["email"],
            "verify_url" => "http://{$_SERVER['HTTP_HOST']}{$this->config['base']}auth/verify"
        ));

        // DEBUG stuff only on development instance
        if (isset($forgottenPassword["verification_link"]))
            throw new Exception($forgottenPassword["verification_link"]);

        $this->redirect("confirm-email");
    }

    public function verifyHandler() {
        $this->enableJson();
        $this->requireMethod("GET");
        $this->requireParams(array("user_id", "hash"));

        $verify = $this->api->call("/auth/verify", array(
            "user_id" => $this->params["user_id"],
            "hash" => $this->params["hash"],
        ));

        // save web session
        if (!isset($verify["session"]))
            throw new Exception("Missing session");

        $session = $verify["session"];
        Utils::createAuthCookie($session, false);
        $this->redirect();
    }

    public function logoutHandler() {
        $this->requireLogin();
        $this->api->call("/auth/logout");
        Utils::destroyAuthCookie();
        $this->redirect();
    }
}