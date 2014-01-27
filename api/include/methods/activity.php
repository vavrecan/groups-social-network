<?php

class Activity extends LoggedUserMethodHandler
{
    const ACTIVITY_USER_FOLLOW = 1;
    const ACTIVITY_USER_UNFOLLOW = 2;
    const ACTIVITY_USER_CREATE = 3;
    const ACTIVITY_USER_UPDATE = 4;

    const ACTIVITY_VOTE = 7;
    const ACTIVITY_COMMENT = 8;
    const ACTIVITY_COMMENT_DELETE = 9;

    const ACTIVITY_GROUP_JOIN = 10;
    const ACTIVITY_GROUP_LEAVE = 11;
    const ACTIVITY_GROUP_CREATE = 12;
    const ACTIVITY_GROUP_UPDATE = 13;
    const ACTIVITY_GROUP_DELETE = 14;
    const ACTIVITY_GROUP_FEED_POST = 15;
    const ACTIVITY_GROUP_FEED_DELETE = 16;

    const ACTIVITY_ARTICLE_CREATE = 30;
    const ACTIVITY_ARTICLE_UPDATE = 31;
    const ACTIVITY_ARTICLE_DELETE = 32;

    const ACTIVITY_EVENT_CREATE = 40;
    const ACTIVITY_EVENT_DELETE = 41;
    const ACTIVITY_EVENT_UPDATE = 42;
    const ACTIVITY_EVENT_ATTEND = 43;
    const ACTIVITY_EVENT_MISS = 44;

    const ACTIVITY_GALLERY_CREATE = 50;
    const ACTIVITY_GALLERY_DELETE = 51;
    const ACTIVITY_GALLERY_UPDATE = 52;

    private static $activityTypes = array(
        self::ACTIVITY_USER_FOLLOW => "user_follow",
        self::ACTIVITY_USER_UNFOLLOW => "user_unfollow",
        self::ACTIVITY_USER_CREATE => "profile_created",
        self::ACTIVITY_USER_UPDATE => "profile_update",

        self::ACTIVITY_VOTE => "vote",
        self::ACTIVITY_COMMENT => "comment",
        self::ACTIVITY_COMMENT_DELETE => "comment_delete",

        self::ACTIVITY_GROUP_JOIN => "group_join",
        self::ACTIVITY_GROUP_LEAVE => "group_leave",
        self::ACTIVITY_GROUP_CREATE => "group_create",
        self::ACTIVITY_GROUP_UPDATE => "group_update",
        self::ACTIVITY_GROUP_DELETE => "group_delete",
        self::ACTIVITY_GROUP_FEED_POST => "group_feed_post",
        self::ACTIVITY_GROUP_FEED_DELETE => "group_feed_delete",

        self::ACTIVITY_ARTICLE_CREATE => "article_create",
        self::ACTIVITY_ARTICLE_UPDATE => "article_update",
        self::ACTIVITY_ARTICLE_DELETE => "article_delete",

        self::ACTIVITY_EVENT_CREATE => "event_create",
        self::ACTIVITY_EVENT_DELETE => "event_delete",
        self::ACTIVITY_EVENT_UPDATE => "event_update",
        self::ACTIVITY_EVENT_ATTEND => "event_attend",
        self::ACTIVITY_EVENT_MISS => "event_miss",

        self::ACTIVITY_GALLERY_CREATE => "gallery_create",
        self::ACTIVITY_GALLERY_DELETE => "gallery_delete",
        self::ACTIVITY_GALLERY_UPDATE => "gallery_update",
    );

    const ACTIVITY_LIST_TYPE_EVERYTHING = 1;
    const ACTIVITY_LIST_TYPE_OTHERS_ONLY = 2;
    const ACTIVITY_LIST_TYPE_ME_ONLY = 3;
    const ACTIVITY_LIST_TYPE_USER = 4;

    private static $activityListTypes = array(
        self::ACTIVITY_LIST_TYPE_EVERYTHING => "everything",
        self::ACTIVITY_LIST_TYPE_OTHERS_ONLY => "others_only",
        self::ACTIVITY_LIST_TYPE_ME_ONLY => "me_only",
        self::ACTIVITY_LIST_TYPE_USER => "user",
    );

    public function listHandler() {
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

        $listType = self::ACTIVITY_LIST_TYPE_EVERYTHING;
        if (isset($this->params["list_type"])) {
            $listType = array_search($this->params["list_type"], self::$activityListTypes);
        }

        $followingIds = $this->database->fetchAllColumn("SELECT follow_user_id FROM user_follow
            WHERE user_id = :user_id", array("user_id" => $this->userId));

        // where follower or FALSE
        $where = (count($followingIds) > 0 ? " activity.user_id IN (" . join(",", $followingIds) .")" : "FALSE");

        if ($listType == self::ACTIVITY_LIST_TYPE_EVERYTHING)
            $where = "activity.user_id = " . (int)$this->userId . " OR " . $where;

        if ($listType == self::ACTIVITY_LIST_TYPE_ME_ONLY)
            $where = "activity.user_id = " . (int)$this->userId;

        if ($listType == self::ACTIVITY_LIST_TYPE_USER) {
            $userId = isset($this->params["user_id"]) ? $this->params["user_id"] : 0;
            $where = "activity.user_id = " . (int)$userId;
        }

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $activities = $this->database->fetchAll("SELECT
                activity.id,
                activity.time,
                activity.activity_type_id,
                activity.type_id,
                activity.object_id as object_id,

                users.id as user_id, users.name as user_name, users.image as user_image,

                groups.id as group_id,
                groups.title as group_title,
                groups.image as group_image
            FROM activity
                INNER JOIN users ON users.id = activity.user_id
                LEFT JOIN groups ON groups.id = activity.group_id
            WHERE (" . $where . ") " .
            ($sinceId > 0 ? " AND activity.id > {$sinceId} " : "").
            ($untilId > 0 ? " AND activity.id < {$untilId} " : "").
            "ORDER BY activity.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}",
            array()
        );

        if (count($activities) > $limit) {
            array_pop($activities);
            $hasMore = true;
        }

        foreach ($activities as &$activity) {
            $this->formatDetail($activity);
        }

        $this->response["data"] = $activities;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("activity_id");
        $activityId = $this->params["activity_id"];

        $activity = $this->database->fetch("SELECT
                activity.id,
                activity.time,
                activity.activity_type_id,
                activity.type_id,
                activity.object_id as object_id,
                groups.id as group_id,
                groups.title as group_title,
                groups.image as group_image
            FROM activity
                LEFT JOIN groups ON groups.id = activity.group_id
            WHERE activity.id = :activity_id AND activity.user_id = :user_id",
            array(
                "activity_id" => $activityId,
                "user_id" => $this->userId
            )
        );

        if (!$activity)
            throw new Exception("No such post");

        $this->formatDetail($activity);

        $this->response = $activity;
        $this->output();
    }

    public static function create(Database $database, $userId, $activityTypeId, $groupId, $typeId = 0, $objectId = 0) {
        $database->exec("INSERT INTO activity(user_id, group_id, time, activity_type_id, type_id, object_id)
            VALUES(:user_id, :group_id, :time, :activity_type_id, :type_id, :object_id)",
            array(
                "user_id" => $userId,
                "group_id" => $groupId,
                "time" => time(),
                "activity_type_id" => $activityTypeId,
                "type_id" => $typeId,
                "object_id" => $objectId,
            )
        );
    }

    private function formatDetail(&$activity) {
        $activity["user"]["id"] = $activity["user_id"];
        $activity["user"]["image"] = Utils::updateImageUrl($activity["user_image"], $this->api->config["images_url"]);
        $activity["user"]["name"] = $activity["user_name"];

        unset($activity["user_id"]);
        unset($activity["user_name"]);
        unset($activity["user_image"]);

        $activity["group"] = array();

        if (!empty($activity["group_id"])) {
            $activity["group"]["id"] = $activity["group_id"];
            $activity["group"]["image"] = Utils::updateImageUrl($activity["group_image"], $this->api->config["images_url"]);
            $activity["group"]["title"] = $activity["group_title"];
        }

        unset($activity["group_id"]);
        unset($activity["group_title"]);
        unset($activity["group_image"]);

        $activity["type"] = $this->objectTypes[$activity["type_id"]];
        unset($activity["type_id"]);

        if (array_key_exists("activity_type_id", $activity)) {
            $activity["activity_type"] = self::$activityTypes[$activity["activity_type_id"]];
            unset($activity["activity_type_id"]);
        }

        $activity["time"] = Utils::updateTime($activity["time"], $this->api->config["time_format"]);
    }
}