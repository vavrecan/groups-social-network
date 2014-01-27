<?php

class User extends ActionHandler {
    protected $requireLogin = false;

    public function indexHandler() {
        $user = $this->getUserDetail();
        $this->response["other_user"] = $user;
        $this->output("user/index");
    }

    private function getUserDetail() {
        $user = $this->api->call("/user/detail", array(
            "user_id" => $this->pathId,
            "image_format" => "p50x50",
            "time_format" => "day",
        ));
        return $user;
    }
}