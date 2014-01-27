<?php

class Voting extends LoggedUserMethodHandler
{
    const VOTING_LIKE = 1;
    const VOTING_DISLIKE = 2;

    private static $votingTypes = array(
        self::VOTING_LIKE => "like",
        self::VOTING_DISLIKE => "dislike",
    );

    public $publicMethods = array("list");

    public function listHandler() {
        $this->requireParam("type");
        $this->requireParam("object_id");
        $this->requireParam("voting_type");

        $typeId = $this->getObjectTypeId($this->params["type"]);
        $objectId = $this->params["object_id"];
        $votingTypeId = array_search($this->params["voting_type"], self::$votingTypes);

        if (!$votingTypeId)
            throw new Exception("No such voting type");

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
        $votings = $this->database->fetchAll("SELECT
                voting.voting_type, voting.created,
                voting.user_id, users.name as user_name, users.image as user_image
            FROM voting
            INNER JOIN users ON users.id = voting.user_id
            WHERE voting.type_id = :type_id AND voting.object_id = :object_id AND voting.voting_type = :voting_type_id " .
            ($sinceId > 0 ? " AND voting.id > {$sinceId} " : "").
            ($untilId > 0 ? " AND voting.id < {$untilId} " : "").
            "ORDER BY voting.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("type_id" => $typeId, "object_id" => $objectId, "voting_type_id" => $votingTypeId));

        if (count($votings) > $limit) {
            array_pop($votings);
            $hasMore = true;
        }

        foreach ($votings as &$voting) {
            $this->formatDetail($voting);
            $voting["voting_type"] = self::$votingTypes[$voting["voting_type"]];
        }

        $this->response["data"] = $votings;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function voteHandler() {
        $this->requireParam("type");
        $this->requireParam("object_id");
        $this->requireParam("voting_type");

        $votingType = $this->params["voting_type"];
        $votingTypeId = array_search($votingType, self::$votingTypes);
        if (!$votingTypeId)
            throw new Exception("Invalid voting type ID");

        $typeId = $this->getObjectTypeId($this->params["type"]);
        $objectId = $this->params["object_id"];

        if (!$this->objectIsPublic($typeId, $objectId))
            $this->objectRequireMember($typeId, $objectId);

        $votedAlready = $this->database->fetchColumn("SELECT COUNT(1) FROM voting
            WHERE user_id = :user_id AND type_id = :type_id AND object_id = :object_id",
            array(
                "user_id" => $this->userId,
                "type_id" => $typeId,
                "object_id" => $objectId
            )
        );

        if ($votedAlready)
            throw new Exception("Already voted");

        $this->database->beginTransaction();

        $this->database->exec("INSERT INTO voting(voting_type, user_id, created, type_id, object_id)
            VALUES(:voting_type, :user_id, :created, :type_id, :object_id)",
            array(
                "voting_type" => $votingTypeId,
                "user_id" => $this->userId,
                "created" => time(),
                "type_id" => $typeId,
                "object_id" => $objectId
            )
        );

        $votingId = $this->database->lastInsertId();

        // update summary
        $updateColumn = false;

        if ($votingTypeId == self::VOTING_LIKE)
            $updateColumn = "`like`";
        if ($votingTypeId == self::VOTING_DISLIKE)
            $updateColumn = "dislike";

        if ($updateColumn) {
            $this->database->exec("INSERT INTO voting_summary(type_id, object_id, `like`, dislike) VALUES (:type_id, :object_id, :like, :dislike)
                ON DUPLICATE KEY UPDATE {$updateColumn} = {$updateColumn} + 1",
                array(
                    "type_id" => $typeId,
                    "object_id" => $objectId,
                    "like" => ($updateColumn == "`like`" ? 1 : 0),
                    "dislike" => ($updateColumn == "dislike" ? 1 : 0),
                )
            );
        }

        $this->database->commit();

        $groupId = $this->objectGetGroupId($typeId, $objectId);
        $ownerId = $this->objectGetUserId($typeId, $objectId);

        $this->logActivity(Activity::ACTIVITY_VOTE, $groupId, $typeId, $objectId);
        $this->notificationCreate($ownerId, $this->userId, $groupId, Notification::NOTIFICATION_VOTE, $typeId, $objectId);

        $this->response["voting_id"] = $votingId;
        $this->response["success"] = 1;
        $this->output();
    }

    private function formatDetail(&$voting) {
        $voting["user"]["id"] = $voting["user_id"];
        $voting["user"]["image"] = Utils::updateImageUrl($voting["user_image"], $this->api->config["images_url"]);
        $voting["user"]["name"] = $voting["user_name"];

        unset($voting["user_id"]);
        unset($voting["user_name"]);
        unset($voting["user_image"]);

        $voting["created"] = Utils::updateTime($voting["created"], $this->api->config["time_format"]);
    }

    private function votingIsOwner($votingId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_VOTE, $votingId);
    }

    private function votingRequireOwner($votingId) {
        $this->objectRequireOwner(self::OBJECT_TYPE_VOTE, $votingId);
    }
}