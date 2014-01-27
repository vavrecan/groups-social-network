<?php

class Groups extends ActionHandler {
    protected $requireLogin = true;

    public function listHandler() {
        $this->output("groups/list");
    }

    public function createHandler() {
        $this->output("groups/create");
    }
}