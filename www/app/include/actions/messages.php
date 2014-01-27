<?php

class Messages extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->output("messages/list");
    }
}