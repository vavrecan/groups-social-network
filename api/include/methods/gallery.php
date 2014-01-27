<?php

class Gallery extends LoggedUserMethodHandler
{
    public $publicMethods = array("detail", "list", "listImages", "detailImage");

    public function listHandler() {
        $this->requireParam("group_id");

        $groupId = $this->params["group_id"];

        $isMember = $this->groupIsMember($groupId);
        $isAdmin = $this->groupIsAdmin($groupId);

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $galleries = $this->database->fetchAll("SELECT
                gallery.id, gallery.title, gallery.created, gallery.time, gallery.visibility,
                users.id  as user_id, users.name as user_name, users.image as user_image,
                (SELECT COUNT(1) FROM gallery_images WHERE gallery_id = gallery.id) as image_count,
                (SELECT image FROM gallery_images WHERE gallery_id = gallery.id ORDER BY time DESC LIMIT 1) as image,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM gallery
                INNER JOIN users ON users.id = gallery.user_id

                LEFT JOIN comment_summary ON (comment_summary.object_id = gallery.id AND comment_summary.type_id = :object_type_gallery)
                LEFT JOIN voting_summary ON (voting_summary.object_id = gallery.id AND voting_summary.type_id = :object_type_gallery)
            WHERE gallery.group_id = :group_id AND gallery.active = 1 AND
                IF(:is_member = 1, 1, IF(gallery.visibility = :visibility_public, 1, 0)) = 1
                ORDER BY gallery.time DESC" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "group_id" => $groupId,
                "object_type_gallery" => self::OBJECT_TYPE_GALLERY,
                "visibility_public" => self::VISIBILITY_PUBLIC,
                "is_member" => (int)$isMember
            )
        );

        if (count($galleries) > $limit) {
            array_pop($galleries);
            $hasMore = true;
        }

        foreach ($galleries as &$gallery) {
            $this->formatDetail($gallery);
            if ($isAdmin)
                $gallery["can_edit"] = true;
        }

        $this->response["data"] = $galleries;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("gallery_id");
        $galleryId = $this->params["gallery_id"];

        $gallery = $this->database->fetch("SELECT
                gallery.id, gallery.title, gallery.created, gallery.time, gallery.group_id, gallery.visibility,
                users.id as user_id, users.name as user_name, users.image as user_image,
                (SELECT COUNT(1) FROM gallery_images WHERE gallery_id = gallery.id) as image_count,
                (SELECT image FROM gallery_images WHERE gallery_id = gallery.id ORDER BY time DESC LIMIT 1) as image,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM gallery
                INNER JOIN users ON users.id = gallery.user_id

                LEFT JOIN comment_summary ON (comment_summary.object_id = gallery.id AND comment_summary.type_id = :object_type_gallery)
                LEFT JOIN voting_summary ON (voting_summary.object_id = gallery.id AND voting_summary.type_id = :object_type_gallery)
            WHERE gallery.id = :gallery_id AND gallery.active = 1",
            array("gallery_id" => $galleryId, "object_type_gallery" => self::OBJECT_TYPE_GALLERY));

        if (!$gallery)
            throw new Exception("No such gallery");

        if ($gallery["visibility"] != self::VISIBILITY_PUBLIC)
            $this->groupRequireMember($gallery["group_id"]);

        $this->formatDetail($gallery);

        if ($this->groupIsAdmin($gallery["group_id"]))
            $gallery["can_edit"] = true;

        $this->response = $gallery;
        $this->output();
    }

    public function createHandler() {
        $this->requireParam("title");
        $this->requireParam("group_id");

        $title = $this->params["title"];
        $groupId = $this->params["group_id"];

        // check permissions
        if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_ADMIN_GALLERY_ONLY))
            $this->groupRequireAdmin($groupId);
        else
            $this->groupRequireMember($groupId);

        // check inputs
        if (empty($title) || strlen($title) < 2)
            throw new Exception("Title is too short");

        $visibility = self::VISIBILITY_PRIVATE;

        // change visibility
        if (isset($this->params["visibility"]))
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);

        // create gallery
        $this->database->exec("INSERT INTO gallery(title, active, created, user_id, group_id, time, visibility)
                                          VALUES (:title, :active, :created, :user_id, :group_id, :created, :visibility)",
            array(
                "title" => $title,
                "active" => "1",
                "created" => time(),
                "user_id" => $this->userId,
                "group_id" => $groupId,
                "visibility" => $visibility
            ));

        $galleryId = $this->database->lastInsertId();

        $this->logActivity(Activity::ACTIVITY_GALLERY_CREATE, $groupId, self::OBJECT_TYPE_GALLERY, $galleryId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_GALLERY, $galleryId,
            $this->resolveAggregation($galleryId, "create"));

        $this->response["gallery_id"] = $galleryId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("gallery_id");

        $galleryId = $this->params["gallery_id"];
        $groupId = $this->galleryGetGroupId($galleryId);

        if (!$this->galleryIsOwner($galleryId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $this->database->exec("UPDATE gallery SET active = :active WHERE id = :gallery_id",
            array("active" => 0, "gallery_id" => $galleryId));

        $this->logActivity(Activity::ACTIVITY_GALLERY_DELETE, $groupId, self::OBJECT_TYPE_GALLERY, $galleryId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function updateHandler() {
        $this->requireParam("gallery_id");

        $galleryId = $this->params["gallery_id"];
        $groupId = $this->galleryGetGroupId($galleryId);

        if (!$this->galleryIsOwner($galleryId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $allowedColumns = array("title");

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $this->database->exec("UPDATE gallery SET gallery.{$column} = :value WHERE id = :gallery_id",
                    array("value" => $this->params[$column], "gallery_id" => $galleryId));
            }
        }

        // update visibility
        if (isset($this->params["visibility"])) {
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);
            $this->database->exec("UPDATE gallery SET gallery.visibility = :visibility WHERE id = :gallery_id",
                array("visibility" => $visibility, "gallery_id" => $galleryId)
            );
        }

        // update modification time
        $this->database->exec("UPDATE gallery SET gallery.time = :time WHERE id = :gallery_id",
            array("time" => time(), "gallery_id" => $galleryId));

        $this->logActivity(Activity::ACTIVITY_GALLERY_UPDATE, $groupId, self::OBJECT_TYPE_GALLERY, $galleryId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_GALLERY, $galleryId,
            $this->resolveAggregation($galleryId, "update"));

        $this->response["success"] = 1;
        $this->output();
    }

    public function addImageHandler() {
        $this->requireParam("gallery_id");
        $this->requireParam("message");

        $galleryId = $this->params["gallery_id"];
        $groupId = $this->galleryGetGroupId($galleryId);

        if (!$this->galleryIsOwner($galleryId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $message = $this->params["message"];

        if (!isset($_FILES["image"]))
            throw new Exception("Image file upload missing");

        $uploadPath = $this->api->config["images_upload_path"];
        $fileName = Utils::saveImage($_FILES["image"]["tmp_name"], $uploadPath, self::IMAGE_ID_GALLERY_IMAGE . $galleryId);

        // save new image
        $this->database->exec("INSERT INTO gallery_images (message, gallery_id, image, time) VALUES(:message, :gallery_id, :image, :time)",
            array("gallery_id" => $galleryId, "image" => $fileName, "message" => $message, "time" => time()));

        $imageId = $this->database->lastInsertId();

        // update modification time
        $this->database->exec("UPDATE gallery SET gallery.time = :time WHERE id = :gallery_id",
            array("time" => time(), "gallery_id" => $galleryId));

        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_GALLERY, $galleryId,
            $this->resolveAggregation($galleryId, "add_image"));

        $this->response["image"] = $this->api->config["images_url"] . $fileName;
        $this->response["image_id"] = $imageId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function listImagesHandler() {
        $this->requireParam("gallery_id");

        $gallery = $this->database->fetch("SELECT gallery.visibility, gallery.group_id
            FROM gallery WHERE id = :gallery_id", array("gallery_id" => $this->params["gallery_id"]));

        if (!$gallery)
            throw new Exception("No such gallery");

        $groupId = $gallery["group_id"];
        $isMember = $this->groupIsMember($groupId);

        if ($gallery["visibility"] != self::VISIBILITY_PUBLIC && !$isMember)
            throw new Exception("No permissions");

        $galleryId = $this->params["gallery_id"];
        $limit = 10;
        $offset = 0;
        $sinceId = 0;
        $untilId = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        if (isset($this->params["since_id"]))
            $sinceId = (int)$this->params["since_id"];

        if (isset($this->params["until_id"]))
            $untilId = (int)$this->params["until_id"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $galleryImages = $this->database->fetchAll("SELECT
                gallery_images.id, gallery_images.message, gallery_images.image, gallery_images.time,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM gallery_images
                LEFT JOIN comment_summary ON (comment_summary.object_id = gallery_images.id AND comment_summary.type_id = :object_type_gallery_image)
                LEFT JOIN voting_summary ON (voting_summary.object_id = gallery_images.id AND voting_summary.type_id = :object_type_gallery_image)
            WHERE gallery_images.gallery_id = :gallery_id " .
                ($sinceId > 0 ? " AND gallery_images.id > {$sinceId} " : "") .
                ($untilId > 0 ? " AND gallery_images.id < {$untilId} " : "") .
                " ORDER BY gallery_images.id DESC" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("gallery_id" => $galleryId, "object_type_gallery_image" => self::OBJECT_TYPE_GALLERY_IMAGE));

        if (count($galleryImages) > $limit) {
            array_pop($galleryImages);
            $hasMore = true;
        }

        foreach ($galleryImages as &$image) {
            $this->formatImageDetail($image);
            $image["image_full"] = Utils::updateImageUrl($image["image"], $this->api->config["images_formats"]["org"]);
            $image["image"] = Utils::updateImageUrl($image["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $galleryImages;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailImageHandler() {
        $this->requireParam("image_id");

        $imageId = $this->params["image_id"];

        $image = $this->database->fetch("SELECT
                gallery_images.id, gallery_images.message, gallery_images.image, gallery_images.time, gallery_images.gallery_id,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM gallery_images
                LEFT JOIN comment_summary ON (comment_summary.object_id = gallery_images.id AND comment_summary.type_id = :object_type_gallery_image)
                LEFT JOIN voting_summary ON (voting_summary.object_id = gallery_images.id AND voting_summary.type_id = :object_type_gallery_image)
            WHERE gallery_images.id = :image_id",
            array("image_id" => $imageId, "object_type_gallery_image" => self::OBJECT_TYPE_GALLERY_IMAGE));

        if (!$image)
            throw new Exception("No such image");

        // get gallery detail
        $gallery = $this->database->fetch("SELECT gallery.visibility, gallery.group_id
            FROM gallery WHERE id = :gallery_id", array("gallery_id" => $image["gallery_id"]));

        if (!$gallery)
            throw new Exception("No such gallery");

        $groupId = $gallery["group_id"];
        $isMember = $this->groupIsMember($groupId);

        if ($gallery["visibility"] != self::VISIBILITY_PUBLIC && !$isMember)
            throw new Exception("No permissions");

        $this->formatImageDetail($image);
        $image["image_full"] = Utils::updateImageUrl($image["image"], $this->api->config["images_formats"]["org"]);
        $image["image"] = Utils::updateImageUrl($image["image"], $this->api->config["images_url"]);

        $this->response = $image;
        $this->output();
    }

    public function updateImageHandler() {
        $this->requireParam("image_id");

        $imageId = $this->params["image_id"];
        $groupId = $this->galleryImageGetGroupId($imageId);

        // require owner or admin of the group
        if (!$this->galleryImageIsOwner($imageId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $allowedColumns = array("message");

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $this->database->exec("UPDATE gallery_images SET gallery_images.{$column} = :value WHERE id = :image_id",
                    array("value" => $this->params[$column], "image_id" => $imageId));
            }
        }

        // update modification time
        $this->database->exec("UPDATE gallery_images SET gallery_images.time = :time WHERE id = :image_id",
            array("time" => time(), "image_id" => $imageId));

        // also on gallery
        $galleryId = (int)$this->database->fetchColumn("SELECT gallery_id FROM gallery_images WHERE id = :image_id",
            array("image_id" => $imageId));

        $this->database->exec("UPDATE gallery SET gallery.time = :time WHERE id = :gallery_id",
            array("time" => time(), "gallery_id" => $galleryId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteImageHandler() {
        $this->requireParam("image_id");

        $imageId = $this->params["image_id"];
        $groupId = $this->galleryImageGetGroupId($imageId);

        // require owner or admin of the group
        if (!$this->galleryImageIsOwner($imageId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $this->database->exec("DELETE FROM gallery_images WHERE id = :image_id", array("image_id" => $imageId));

        $this->response["success"] = 1;
        $this->output();
    }

    private function galleryIsOwner($galleryId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_GALLERY, $galleryId);
    }

    private function galleryGetGroupId($galleryId) {
        return $this->objectGetGroupId(self::OBJECT_TYPE_GALLERY, $galleryId);
    }

    private function galleryImageIsOwner($imageId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_GALLERY_IMAGE, $imageId);
    }

    private function galleryImageGetGroupId($imageId) {
        return $this->objectGetGroupId(self::OBJECT_TYPE_GALLERY_IMAGE, $imageId);
    }

    private function formatDetail(&$gallery) {
        $gallery["user"]["id"] = $gallery["user_id"];
        $gallery["user"]["image"] = Utils::updateImageUrl($gallery["user_image"], $this->api->config["images_url"]);
        $gallery["user"]["name"] = $gallery["user_name"];

        unset($gallery["user_id"]);
        unset($gallery["user_name"]);
        unset($gallery["user_image"]);

        if (array_key_exists("visibility", $gallery)) {
            $gallery["visibility"] = self::$visibilityTypes[$gallery["visibility"]];
        }

        $gallery["can_edit"] = (int)$gallery["user"]["id"] == (int)$this->userId;
        $gallery["time"] = Utils::updateTime($gallery["time"], $this->api->config["time_format"]);
        $gallery["created"] = Utils::updateTime($gallery["created"], $this->api->config["time_format"]);
        $gallery["can_interact"] = $this->userId != null;
        $gallery["image"] = Utils::updateImageUrl($gallery["image"], $this->api->config["images_url"]);
    }

    private function formatImageDetail(&$image) {
        $image["comment_count"] = (int)$image["comment_count"];
        $image["like_count"] = (int)$image["like_count"];
        $image["dislike_count"] = (int)$image["dislike_count"];

        $image["time"] = Utils::updateTime($image["time"], $this->api->config["time_format"]);
        $image["can_interact"] = $this->userId != null;
    }

    private function resolveAggregation($galleryId, $action) {
        $gallery = $this->database->fetch("SELECT title FROM gallery
            WHERE id = :gallery_id", array("gallery_id"=> $galleryId));

        return $gallery + array(
            "type" => $this->objectTypes[self::OBJECT_TYPE_GALLERY],
            "id" => $galleryId,
            "action" => $action
        );
    }
}