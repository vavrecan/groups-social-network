<?php

class Notifications extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->output("notifications/list");
    }
}