<?php

class Comment extends LoggedUserMethodHandler
{
    public $publicMethods = array("list");

    public function listHandler() {
        $this->requireParam("type");
        $this->requireParam("object_id");

        $typeId = $this->getObjectTypeId($this->params["type"]);
        $objectId = $this->params["object_id"];

        if (!$this->objectIsPublic($typeId, $objectId))
            $this->objectRequireMember($typeId, $objectId);

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
        $comments = $this->database->fetchAll("SELECT
                comments.id, comments.message, comments.created,
                comments.user_id, users.name as user_name, users.image as user_image,

                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM comments
                INNER JOIN users ON users.id = comments.user_id
                LEFT JOIN voting_summary ON (voting_summary.object_id = comments.id AND voting_summary.type_id = :object_type_comment)
            WHERE comments.type_id = :type_id AND comments.object_id = :object_id AND comments.active = 1 " .
            ($sinceId > 0 ? " AND comments.id > {$sinceId} " : "").
            ($untilId > 0 ? " AND comments.id < {$untilId} " : "").
            "ORDER BY comments.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("type_id" => $typeId, "object_id" => $objectId, "object_type_comment" => self::OBJECT_TYPE_COMMENT));

        if (count($comments) > $limit) {
            array_pop($comments);
            $hasMore = true;
        }

        foreach ($comments as &$comment) {
            $this->formatDetail($comment);
        }

        $this->response["data"] = $comments;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("comment_id");

        $commentId = $this->params["comment_id"];
        $comment = $this->database->fetch("SELECT
                comments.id, comments.message, comments.created,
                comments.type_id, comments.object_id,
                users.id as user_id, users.name as user_name, users.image as user_image,

                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM comments
                INNER JOIN users ON users.id = comments.user_id
                LEFT JOIN voting_summary ON (voting_summary.object_id = comments.id AND voting_summary.type_id = :object_type_comment)
            WHERE comments.id = :comment_id AND comments.active = 1",
            array("comment_id" => $commentId, "object_type_comment" => self::OBJECT_TYPE_COMMENT));

        if (!$comment)
            throw new Exception("Comment not found");

        if (!$this->objectIsPublic($comment["type_id"], $comment["object_id"]))
            $this->objectRequireMember($comment["type_id"], $comment["object_id"]);

        $this->formatDetail($comment);

        $comment["type"] = $this->objectTypes[$comment["type_id"]];
        unset($comment["type_id"]);

        $this->response = $comment;
        $this->output();
    }

    public function postHandler() {
        $this->requireParam("type");
        $this->requireParam("object_id");
        $this->requireParam("message");

        $typeId = $this->getObjectTypeId($this->params["type"]);
        $objectId = $this->params["object_id"];
        $message = $this->params["message"];

        if (strlen(trim($message)) == 0)
            throw new Exception("Message can not be empty");

        if (!$this->objectIsPublic($typeId, $objectId))
            $this->objectRequireMember($typeId, $objectId);

        $this->database->beginTransaction();

        $this->database->exec("INSERT INTO comments(message, user_id, created, type_id, object_id, active)
            VALUES(:message, :user_id, :created, :type_id, :object_id, 1)",
            array(
                "message" => $message,
                "user_id" => $this->userId,
                "created" => time(),
                "type_id" => $typeId,
                "object_id" => $objectId
            )
        );

        $commentId = $this->database->lastInsertId();

        // update summary
        $this->database->exec("INSERT INTO comment_summary(type_id, object_id, count) VALUES (:type_id, :object_id, 1)
            ON DUPLICATE KEY UPDATE count = count + 1",
            array(
                "type_id" => $typeId,
                "object_id" => $objectId
            )
        );

        $this->database->commit();

        $groupId = $this->objectGetGroupId($typeId, $objectId);
        $ownerId = $this->objectGetUserId($typeId, $objectId);

        $this->logActivity(Activity::ACTIVITY_COMMENT, $groupId, $typeId, $objectId);
        $this->notificationCreate($ownerId, $this->userId, $groupId, Notification::NOTIFICATION_COMMENT, $typeId, $objectId);

        $this->response["comment_id"] = $commentId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("comment_id");
        $this->commentRequireOwner($this->params["comment_id"]);

        $commentId = $this->params["comment_id"];

        // get type id and object id from original comment
        $comment = $this->database->fetch("SELECT type_id, object_id FROM comments WHERE id = :comment_id AND active = 1",
            array("comment_id" => $commentId));

        if (!$comment)
            throw new Exception("Comment not found");

        $this->database->beginTransaction();
        $this->database->exec("UPDATE comments SET active = 0 WHERE id = :comment_id",
            array("comment_id" => $commentId));

        // update summary
        $this->database->exec("INSERT INTO comment_summary(type_id, object_id, count) VALUES (:type_id, :object_id, 0)
            ON DUPLICATE KEY UPDATE count = GREATEST(0, count - 1)",
            array(
                "type_id" => $comment["type_id"],
                "object_id" => $comment["object_id"]
            )
        );
        $this->database->commit();

        $groupId = $this->objectGetGroupId($comment["type_id"], $comment["object_id"]);
        $this->logActivity(Activity::ACTIVITY_COMMENT_DELETE, $groupId, $comment["type_id"], $comment["object_id"]);
        $this->response["success"] = 1;
        $this->output();
    }

    private function formatDetail(&$comment) {
        $comment["user"]["id"] = $comment["user_id"];
        $comment["user"]["image"] = Utils::updateImageUrl($comment["user_image"], $this->api->config["images_url"]);
        $comment["user"]["name"] = $comment["user_name"];

        unset($comment["user_id"]);
        unset($comment["user_image"]);
        unset($comment["user_name"]);

        $comment["created"] = Utils::updateTime($comment["created"], $this->api->config["time_format"]);
        $comment["can_edit"] = (int)$comment["user"]["id"] === (int)$this->userId;
    }

    private function commentRequireOwner($commentId) {
        $this->objectRequireOwner(self::OBJECT_TYPE_COMMENT, $commentId);
    }
}