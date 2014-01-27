<?php

class Notification extends LoggedUserMethodHandler
{
    const NOTIFICATION_JOIN_REQUEST = 1;
    const NOTIFICATION_VOTE = 3;
    const NOTIFICATION_COMMENT = 5;
    const NOTIFICATION_FOLLOW = 6;
    const NOTIFICATION_EVENT_ATTENDING = 7;
    const NOTIFICATION_MEMBER = 8;

    private static $notificationTypes = array(
        self::NOTIFICATION_JOIN_REQUEST => "join_request",
        self::NOTIFICATION_VOTE => "vote",
        self::NOTIFICATION_COMMENT => "comment",
        self::NOTIFICATION_FOLLOW => "follow",
        self::NOTIFICATION_EVENT_ATTENDING => "event_attending",
        self::NOTIFICATION_MEMBER => "made_member",
    );

    const NOTIFICATION_STATUS_UNREAD = 0;
    const NOTIFICATION_STATUS_READ = 1;

    private static $notificationStatusTypes = array(
        self::NOTIFICATION_STATUS_UNREAD => "unread",
        self::NOTIFICATION_STATUS_READ => "read",
    );

    const NOTIFICATION_AGGREGATION_TIME = 3600;

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

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $notifications = $this->database->fetchAll("SELECT
                notifications.id,
                notifications.time,
                notifications.status,
                notifications.notification_type_id,
                notifications.type_id as type_id,
                notifications.object_id as object_id,
                notifications.from_user_count,

                users.id as user_id, users.name as user_name, users.image as user_image,
                groups.id as group_id, groups.title as group_title, groups.image as group_image
            FROM notifications
                LEFT JOIN groups ON groups.id = notifications.group_id
                LEFT JOIN users ON users.id = notifications.from_user_id
            WHERE notifications.user_id = :user_id " .
            ($sinceId > 0 ? " AND notifications.id > {$sinceId} " : "").
            ($untilId > 0 ? " AND notifications.id < {$untilId} " : "").
            "ORDER BY notifications.time DESC LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "user_id" => $this->userId
            )
        );

        if (count($notifications) > $limit) {
            array_pop($notifications);
            $hasMore = true;
        }

        foreach ($notifications as &$notification) {
            $this->formatDetail($notification);
        }

        // update read range
        if (count($notifications) > 0) {
            // get range
            $fromNotificationId = $notifications[count($notifications) - 1]["id"]; // oldest post
            $toNotificationId = $notifications[0]["id"]; // latest post

            if (isset($this->params["mark_as_read"]) && $this->params["mark_as_read"] == "1")
                $this->markAsRead($fromNotificationId, $toNotificationId);
        }

        $unreadCount = (int)$this->database->fetchColumn("SELECT notification_count - read_count FROM notification_summary WHERE user_id = :user_id",
            array("user_id" => $this->userId));

        $this->response["data"] = $notifications;
        $this->response["has_more"] = $hasMore;
        $this->response["unread"] = $unreadCount;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("notification_id");
        $notificationId = $this->params["notification_id"];

        $notification = $this->database->fetch("SELECT
                notifications.id,
                notifications.time,
                notifications.status,
                notifications.notification_type_id,
                notifications.type_id as type_id,
                notifications.object_id as object_id,
                notifications.from_user_count,

                users.id as user_id, users.name as user_name, users.image as user_image,
                groups.id as group_id, groups.title as group_title, groups.image as group_image
            FROM notifications
                LEFT JOIN groups ON groups.id = notifications.group_id
                LEFT JOIN users ON users.id = notifications.from_user_id
            WHERE notifications.id = :notification_id AND notifications.user_id = :user_id",
            array(
                "notification_id" => $notificationId,
                "user_id" => $this->userId
            )
        );

        if (!$notification)
            throw new Exception("No such post");

        $this->formatDetail($notification);

        $this->response = $notification;
        $this->output();
    }

    public function createHandler() {
        $this->requireParam("notification_type");
        $this->requireParam("type");
        $this->requireParam("object_id");

        if (!in_array($this->params["notification_type"], self::$notificationTypes))
            throw new Exception("Unsupported notification type");

        $groupId = 0;
        if (isset($this->params["group_id"]))
            $groupId = $this->params["group_id"];

        $fromUserId = 0;
        if (isset($this->params["user_id"]))
            $fromUserId = $this->params["user_id"];

        $notificationTypeId = array_search($this->params["notification_type"], self::$notificationTypes);

        $typeId = array_search($this->params["type"], $this->objectTypes);
        $objectId = $this->params["object_id"];

        $notificationId = $this->notificationCreate($this->userId, $fromUserId, $groupId, $notificationTypeId, $typeId, $objectId);
        $this->response["notification_id"] = $notificationId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function markAsReadHandler() {
        $this->requireParam("from_notification_id");
        $this->requireParam("to_notification_id");

        $fromNotificationId = $this->params["from_notification_id"];
        $toNotificationId = $this->params["to_notification_id"];

        $this->markAsRead($fromNotificationId, $toNotificationId);

        $this->response["success"] = 1;
        $this->output();
    }

    private function markAsRead($fromNotificationId, $toNotificationId) {
        if ($fromNotificationId > $toNotificationId)
            throw new Exception("Range is invalid");

        // get notifications that are not read
        $unreadNotificationIds = $this->database->fetchAllColumn("SELECT id
            FROM notifications
            WHERE
                notifications.user_id = :user_id
                AND (IFNULL(notifications.status, 0) <> :notification_status_read)
                AND notifications.id BETWEEN :from_notification_id AND :to_notification_id",
            array(
                "from_notification_id" => $fromNotificationId,
                "to_notification_id" => $toNotificationId,
                "user_id" => $this->userId,
                "notification_status_read" => self::NOTIFICATION_STATUS_READ
            )
        );


        if (count($unreadNotificationIds) > 0) {
            $this->database->beginTransaction();

            // mark as read
            foreach ($unreadNotificationIds as $notificationId) {
                $this->database->exec("UPDATE notifications SET status = :status
                WHERE id = :notification_id AND user_id = :user_id",
                    array("user_id" => $this->userId, "notification_id" => $notificationId, "status" => self::NOTIFICATION_STATUS_READ));
            }

            // update summary
            $this->database->exec("INSERT INTO notification_summary (user_id, notification_count, read_count)
                    VALUES(:user_id, 0, :read_count) ON DUPLICATE KEY UPDATE
                        read_count = read_count + :read_count",
                array(
                    "user_id" => $this->userId,
                    "read_count" => count($unreadNotificationIds)
                )
            );

            $this->database->commit();
        }
    }

    private function formatDetail(&$notification) {
        $notification["group"] = array();

        if (!empty($notification["group_id"])) {
            $notification["group"]["id"] = $notification["group_id"];
            $notification["group"]["image"] = Utils::updateImageUrl($notification["group_image"], $this->api->config["images_url"]);
            $notification["group"]["title"] = $notification["group_title"];
        }

        unset($notification["group_id"]);
        unset($notification["group_title"]);
        unset($notification["group_image"]);

        $notification["user"] = array();

        if (!empty($notification["user_id"])) {
            $notification["user"]["id"] = $notification["user_id"];
            $notification["user"]["image"] = Utils::updateImageUrl($notification["user_image"], $this->api->config["images_url"]);
            $notification["user"]["name"] = $notification["user_name"];
        }

        unset($notification["user_id"]);
        unset($notification["user_name"]);
        unset($notification["user_image"]);


        if (array_key_exists("notification_type_id", $notification)) {
            $notification["notification_type"] = self::$notificationTypes[$notification["notification_type_id"]];
            unset($notification["notification_type_id"]);
        }

        $notification["time"] = Utils::updateTime($notification["time"], $this->api->config["time_format"]);
        $notification["status"] = self::$notificationStatusTypes[$notification["status"]];
        $notification["type"] = $notification["type_id"] > 0 ? $this->objectTypes[$notification["type_id"]] : "";

        unset($notification["type_id"]);
    }

    public static function create(Database $database, $userId, $fromUserId, $groupId, $notificationTypeId, $typeId, $objectId) {
        // there is no one to create notification to
        if (empty($userId))
            return -1;

        // ignore if notification to is same as notification from
        if ((int)$userId == (int)$fromUserId)
            return -1;

        // aggregate by user, notification type, type and object
        // check for aggregated post
        $notification = $database->fetch("SELECT id, status
            FROM notifications WHERE  user_id = :user_id AND group_id = :group_id AND
                notification_type_id = :notification_type_id AND object_id = :object_id AND type_id = :type_id
                AND time > :time",
            array(
                "user_id" => $userId,
                "group_id" => $groupId,
                "notification_type_id" => $notificationTypeId,
                "type_id" => $typeId,
                "object_id" => $objectId,
                "time" => time() - self::NOTIFICATION_AGGREGATION_TIME
            )
        );

        if ($notification) {
            $notificationId = $notification["id"];

            // if status is read set as unread
            if ($notification["status"] == self::NOTIFICATION_STATUS_READ) {
                self::markAsUnread($database, $notificationId, $userId);
            }
        }
        else {
            $database->beginTransaction();

            // create group
            $database->exec("INSERT INTO notifications(user_id, group_id, status, notification_type_id, type_id, object_id, time, from_user_count, from_user_id)
                                          VALUES (:user_id, :group_id, :status, :notification_type_id, :type_id, :object_id, :time, 0, 0)",
                array(
                    "user_id" => $userId,
                    "group_id" => $groupId,
                    "status" => self::NOTIFICATION_STATUS_UNREAD,
                    "notification_type_id" => $notificationTypeId,
                    "object_id" => $objectId,
                    "type_id" => $typeId,
                    "time" => time(),
                )
            );

            $notificationId = $database->lastInsertId();

            // update summary
            $database->exec("INSERT INTO notification_summary(user_id, notification_count, read_count) VALUES (:user_id, 1, 0)
            ON DUPLICATE KEY UPDATE notification_count = notification_count + 1",
                array("user_id" => $userId));

            $database->commit();
        }

        // add user to notification
        if ($fromUserId > 0) {
            self::addSourceUser($database, $notificationId, $fromUserId);
        }

        return $notificationId;
    }

    public static function addSourceUser($database, $notificationId, $fromUserId) {
        $database->beginTransaction();

        // add user to notification summary list
        $rowCount = $database->exec("INSERT IGNORE INTO notification_users(notification_id, user_id)
                                          VALUES (:notification_id, :user_id)",
            array(
                "notification_id" => $notificationId,
                "user_id" => $fromUserId,
            ), true
        );

        if ($rowCount > 0) {
            // update summary
            $database->exec("UPDATE notifications SET from_user_count = from_user_count + 1, from_user_id = :user_id WHERE id = :notification_id",
                array("notification_id" => $notificationId, "user_id" => $fromUserId));
        }

        $database->commit();
    }

    public static function markAsUnread($database, $notificationId, $userId) {
        $database->beginTransaction();

        // update status to unread
        $database->exec("UPDATE notifications SET status = :status, time = :time WHERE id = :notification_id",
            array("notification_id" => $notificationId, "status" => self::NOTIFICATION_STATUS_UNREAD, "time" => time()));

        // summary
        $database->exec("INSERT INTO notification_summary(user_id, notification_count, read_count) VALUES (:user_id, 0, 0)
            ON DUPLICATE KEY UPDATE read_count = read_count - 1",
            array("user_id" => $userId));

        $database->commit();
    }
}