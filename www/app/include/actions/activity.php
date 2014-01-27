<?php

class Activity extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->output("activity/list");
    }
}