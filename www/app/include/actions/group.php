<?php

class Group extends ActionHandler {
    protected $requireLogin = false;

    public function indexHandler() {
        $group = $this->getGroupDetail();
        if ($group["is_member"]) {
            $this->redirect("group-{$this->pathId}/feed");
        }
        else {
            $this->redirect("group-{$this->pathId}/detail");
        }
    }

    public function feedHandler() {
        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->output("group/feed");
    }

    public function articlesHandler() {
        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->output("group/articles");
    }

    public function galleryHandler($galleryId) {
        if (is_numeric($galleryId) && $galleryId > 0) {
            $gallery = $this->api->call("/gallery/detail", array(
                "gallery_id" => $galleryId,
                "image_format" => "p50x50",
                "time_format" => "day"
            ));
            $this->response["gallery"] = $gallery;
        }

        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->output("group/gallery");
    }

    public function galleryImageHandler($galleryImageId) {
        $post = $this->api->call("/gallery/detailImage", array(
            "image_id" => $galleryImageId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->response["gallery_image"] = $post;
        $this->output("group/details/gallery-image");
    }

    public function eventsHandler() {
        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->output("group/events");
    }

    public function postHandler($postId) {
        $post = $this->api->call("/feed/detail", array(
            "post_id" => $postId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->response["post"] = $post;
        $this->output("group/details/post");
    }

    public function commentHandler($commentId) {
        $comment = $this->api->call("/comment/detail", array(
            "comment_id" => $commentId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        $type = $comment["type"];
        if ($type == "gallery_image")
            $type = "gallery-image";

        $objectId = $comment["object_id"];
        $this->redirect("group-{$this->pathId}/{$type}-{$objectId}#comment-{$commentId}");
    }

    public function articleHandler($articleId) {
        $article = $this->api->call("/article/detail", array(
            "article_id" => $articleId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->response["article"] = $article;
        $this->output("group/articles");
    }

    public function eventHandler($eventId) {
        $event = $this->api->call("/event/detail", array(
            "event_id" => $eventId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->response["event"] = $event;
        $this->output("group/events");
    }

    public function detailHandler() {
        $group = $this->getGroupDetail();
        $this->response["group"] = $group;
        $this->output("group/detail");
    }

    private function getGroupDetail() {
        $group = $this->api->call("/group/detail", array(
            "group_id" => $this->pathId,
            "image_format" => "p50x50",
            "time_format" => "day"
        ));

        return $group;
    }
}
