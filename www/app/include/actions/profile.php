<?php

class Profile extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->response["months"] = Utils::getMonths();
        $this->response["days"] = Utils::getDays();
        $this->response["years"] = Utils::getYears();
        $this->response["genders"] = Utils::getGenders();
        $this->output("profile/index");
    }
}