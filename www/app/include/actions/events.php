<?php

class Events extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->output("events/list");
    }
}