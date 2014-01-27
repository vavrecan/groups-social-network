<?php

class Search extends ActionHandler {
    protected $requireLogin = true;

    public function indexHandler() {
        $this->output("search/list");
    }
}