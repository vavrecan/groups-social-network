<?php

class Event extends LoggedUserMethodHandler
{
    const EVENT_JOIN_GOING = 1;
    const EVENT_JOIN_NOT_GOING = 2;
    const EVENT_JOIN_MAYBE = 3;

    private static $eventJoinTypes = array(
        self::EVENT_JOIN_GOING => "going",
        self::EVENT_JOIN_NOT_GOING => "not_going",
        self::EVENT_JOIN_MAYBE => "maybe",
    );

    public $publicMethods = array("detail", "list");

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
        $events = $this->database->fetchAll("SELECT
                events.id, events.title, events.message, events.created, events.time, events.time_end, events.visibility,
                users.id as user_id, users.name as user_name, users.image as user_image,

                IFNULL(event_summary.going,0) as going,
                IFNULL(event_summary.not_going,0) as not_going,
                IFNULL(event_summary.maybe,0) as maybe,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM events
                INNER JOIN users ON users.id = events.user_id
                LEFT JOIN event_summary ON event_summary.event_id = events.id
                LEFT JOIN comment_summary ON (comment_summary.object_id = events.id AND comment_summary.type_id = :object_type_event)
                LEFT JOIN voting_summary ON (voting_summary.object_id = events.id AND voting_summary.type_id = :object_type_event)
            WHERE events.group_id = :group_id AND events.active = 1 AND
                IF(:is_member = 1, 1, IF(events.visibility = :visibility_public, 1, 0)) = 1
                ORDER BY events.time DESC" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "group_id" => $groupId,
                "object_type_event" => self::OBJECT_TYPE_EVENT,
                "visibility_public" => self::VISIBILITY_PUBLIC,
                "is_member" => (int)$isMember
            )
        );

        if (count($events) > $limit) {
            array_pop($events);
            $hasMore = true;
        }

        foreach ($events as &$event) {
            $this->formatDetail($event);
            if ($isAdmin)
                $post["can_edit"] = true;
        }

        $this->response["data"] = $events;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listAttendingHandler() {
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $events = $this->database->fetchAll("SELECT
                events.id, events.title, events.message, events.created, events.time, events.time_end,
                events.group_id,
                event_attendants.created as attending_created, event_attendants.type as attending,

                IFNULL(event_summary.going,0) as going,
                IFNULL(event_summary.not_going,0) as not_going,
                IFNULL(event_summary.maybe,0) as maybe
            FROM event_attendants
            INNER JOIN events ON event_attendants.event_id = events.id
            LEFT JOIN event_summary ON event_summary.event_id = events.id
            WHERE event_attendants.user_id = :user_id AND events.active = 1 AND event_attendants.type <> :not_going
                ORDER BY events.time DESC" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "user_id" => $this->userId,
                "not_going" => self::EVENT_JOIN_NOT_GOING // exclude events we are not going to
            )
        );

        if (count($events) > $limit) {
            array_pop($events);
            $hasMore = true;
        }

        foreach ($events as &$event) {
            $event["time"] = Utils::updateTime($event["time"], $this->api->config["time_format"]);
            $event["time_end"] = Utils::updateTime($event["time_end"], $this->api->config["time_format"]);
            $event["attending_created"] = Utils::updateTime($event["attending_created"], $this->api->config["time_format"]);

            if (array_key_exists($event["attending"], self::$eventJoinTypes))
                $event["attending"] = self::$eventJoinTypes[$event["attending"]];
        }

        $this->response["data"] = $events;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("event_id");

        $eventId = $this->params["event_id"];

        $event = $this->database->fetch("SELECT
                events.id, events.title, events.message, events.created, events.time, events.time_end, events.group_id, events.visibility,
                users.id as user_id, users.name as user_name, users.image as user_image,
                events.location_latitude, events.location_longitude, events.location_title,

                IFNULL(event_summary.going,0) as going,
                IFNULL(event_summary.not_going,0) as not_going,
                IFNULL(event_summary.maybe,0) as maybe,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM events
                INNER JOIN users ON users.id = events.user_id
                LEFT JOIN event_summary ON event_summary.event_id = events.id
                LEFT JOIN comment_summary ON (comment_summary.object_id = events.id AND comment_summary.type_id = :object_type_event)
                LEFT JOIN voting_summary ON (voting_summary.object_id = events.id AND voting_summary.type_id = :object_type_event)
            WHERE events.id = :event_id AND events.active = 1",
            array("event_id" => $eventId, "object_type_event" => self::OBJECT_TYPE_EVENT)
        );

        if (!$event)
            throw new Exception("No such event");

        if ($event["visibility"] != self::VISIBILITY_PUBLIC)
            $this->groupRequireMember($event["group_id"]);

        $this->formatDetail($event);

        if ($this->groupIsAdmin($event["group_id"]))
            $event["can_edit"] = true;

        Utils::translateLocation($this->database, $event);

        $event["attending"] = $this->getAttendance($eventId, $this->userId);

        $this->response = $event;
        $this->output();
    }

    public function createHandler() {
        $this->requireParam("title");
        $this->requireParam("message");
        $this->requireParam("time");
        $this->requireParam("group_id");

        $title = $this->params["title"];
        $message = $this->params["message"];
        $time = $this->params["time"];
        $groupId = $this->params["group_id"];
        $visibility = self::VISIBILITY_PRIVATE;

        // check permissions
        if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_ADMIN_EVENTS_ONLY))
            $this->groupRequireAdmin($groupId);
        else
            $this->groupRequireMember($groupId);

        // change visibility
        if (isset($this->params["visibility"]))
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);

        $timeEnd = 0;

        if (isset($this->params["time_end"]))
            $timeEnd = $this->params["time_end"];

        $latitude = 0;
        $longitude = 0;
        $location = "";

        if (isset($this->params["latitude"]))
            $latitude = $this->params["latitude"];

        if (isset($this->params["longitude"]))
            $longitude = $this->params["longitude"];

        if (isset($this->params["location_title"]))
            $location = $this->params["location_title"];

        // check inputs
        if (empty($title) || strlen($title) < 2)
            throw new Exception("Title is too short");

        if (empty($message))
            throw new Exception("Message is empty");

        // create event
        $this->database->exec("INSERT INTO events(title, message, active, visibility, created, user_id, group_id, time, time_end, location_latitude, location_longitude, location_title)
                                          VALUES (:title, :message, :active, :visibility, :created, :user_id, :group_id, :time, :time_end, :latitude, :longitude, :location_title)",
            array(
                "title" => $title,
                "message" => $message,
                "active" => "1",
                "visibility" => $visibility,
                "created" => time(),
                "time" => $time,
                "time_end" => $timeEnd,
                "user_id" => $this->userId,
                "group_id" => $groupId,
                "latitude" => $latitude,
                "longitude" => $longitude,
                "location_title" => $location
            ));

        $eventId = $this->database->lastInsertId();

        $this->logActivity(Activity::ACTIVITY_EVENT_CREATE, $groupId, self::OBJECT_TYPE_EVENT, $eventId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_EVENT, $eventId,
            $this->resolveAggregation($eventId, "create"));

        $this->response["event_id"] = $eventId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("event_id");

        $eventId = $this->params["event_id"];
        $groupId = $this->eventGetGroupId($eventId);

        // require owner or admin of the group
        if (!$this->eventIsOwner($eventId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $this->database->exec("UPDATE events SET active = :active WHERE id = :event_id",
            array("active" => 0, "event_id" => $eventId));

        $this->logActivity(Activity::ACTIVITY_EVENT_DELETE, $groupId, self::OBJECT_TYPE_EVENT, $eventId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function updateHandler() {
        $this->requireParam("event_id");

        $eventId = $this->params["event_id"];
        $groupId = $this->eventGetGroupId($eventId);

        // require owner or admin of the group
        if (!$this->eventIsOwner($eventId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $allowedColumns = array("title", "message", "time", "time_end", "latitude", "longitude", "location_title");

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $mysqlColumn = $column;

                if ($column == "latitude")
                    $mysqlColumn = "location_latitude";

                if ($column == "longitude")
                    $mysqlColumn = "location_longitude";

                $this->database->exec("UPDATE events SET events.{$mysqlColumn} = :value WHERE id = :event_id",
                    array("value" => $this->params[$column], "event_id" => $eventId));
            }
        }

        // update visibility
        if (isset($this->params["visibility"])) {
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);
            $this->database->exec("UPDATE events SET events.visibility = :visibility WHERE id = :event_id",
                array("visibility" => $visibility, "event_id" => $eventId)
            );
        }

        $this->logActivity(Activity::ACTIVITY_EVENT_UPDATE, $groupId, self::OBJECT_TYPE_EVENT, $eventId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_EVENT, $eventId,
            $this->resolveAggregation($eventId, "update"));

        $this->response["success"] = 1;
        $this->output();
    }

    public function attendHandler() {
        $this->requireParam("event_id");
        $this->requireParam("type");
        $this->eventRequireMember($this->params["event_id"]);

        $eventId = $this->params["event_id"];
        $type = $this->params["type"];

        $joinType = array_search($type, self::$eventJoinTypes);
        if (!$joinType)
            throw new Exception("Invalid join type");

        $this->addAttendant($eventId, $this->userId, $joinType);

        $groupId = $this->objectGetGroupId(self::OBJECT_TYPE_EVENT, $eventId);
        $ownerId = $this->objectGetUserId(self::OBJECT_TYPE_EVENT, $eventId);

        $this->logActivity(Activity::ACTIVITY_EVENT_ATTEND, $groupId, self::OBJECT_TYPE_EVENT, $eventId);
        $this->notificationCreate($ownerId, $this->userId, $groupId, Notification::NOTIFICATION_EVENT_ATTENDING, self::OBJECT_TYPE_EVENT, $eventId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function missHandler() {
        $this->requireParam("event_id");
        $this->eventRequireMember($this->params["event_id"]);

        $eventId = $this->params["event_id"];

        $this->removeAttendant($eventId, $this->userId);

        $groupId = $this->objectGetGroupId(self::OBJECT_TYPE_EVENT, $eventId);
        $this->logActivity(Activity::ACTIVITY_EVENT_MISS, $groupId, self::OBJECT_TYPE_EVENT, $eventId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function listUsersHandler() {
        $this->requireParam("event_id");
        $this->requireParam("type");
        $this->eventRequireMember($this->params["event_id"]);

        $eventId = $this->params["event_id"];
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $type = $this->params["type"];

        $joinType = array_search($type, self::$eventJoinTypes);
        if (!$joinType)
            throw new Exception("Invalid join type");

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $attendants = $this->database->fetchAll("SELECT
                users.id as user_id, users.name as user_name, users.image as user_image
            FROM event_attendants
            INNER JOIN users ON users.id = event_attendants.user_id
            WHERE event_attendants.event_id = :event_id AND event_attendants.type = :type" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("event_id" => $eventId, "type" => $joinType));

        if (count($attendants) > $limit) {
            array_pop($attendants);
            $hasMore = true;
        }

        foreach($attendants as &$attendant) {
            $attendant["user"]["id"] = $attendant["user_id"];
            $attendant["user"]["image"] = Utils::updateImageUrl($attendant["user_image"], $this->api->config["images_url"]);
            $attendant["user"]["name"] = $attendant["user_name"];

            unset($attendant["user_id"]);
            unset($attendant["user_image"]);
            unset($attendant["user_name"]);
        }

        $this->response["data"] = $attendants;
        $this->response["type"] = $type;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    private function formatDetail(&$event) {
        $event["user"]["id"] = $event["user_id"];
        $event["user"]["image"] = Utils::updateImageUrl($event["user_image"], $this->api->config["images_url"]);
        $event["user"]["name"] = $event["user_name"];

        unset($event["user_id"]);
        unset($event["user_image"]);
        unset($event["user_name"]);

        if (array_key_exists("visibility", $event)) {
            $event["visibility"] = self::$visibilityTypes[$event["visibility"]];
        }

        $event["can_edit"] = (int)$event["user"]["id"] == (int)$this->userId;

        $event["created"] = Utils::updateTime($event["created"], $this->api->config["time_format"]);
        $event["time"] = Utils::updateTime($event["time"], $this->api->config["time_format"]);
        $event["time_end"] = Utils::updateTime($event["time_end"], $this->api->config["time_format"]);
        $event["can_interact"] = $this->userId != null;
    }

    private function resolveAggregation($eventId, $action) {
        $event = $this->database->fetch("SELECT title FROM events
            WHERE id = :event_id", array("event_id"=> $eventId));

        return $event + array(
            "type" => $this->objectTypes[self::OBJECT_TYPE_EVENT],
            "id" => $eventId,
            "action" => $action
        );
    }

    private function removeAttendant($eventId, $userId) {
        $attendant = $this->database->fetch("SELECT id,type
            FROM event_attendants WHERE user_id = :user_id AND event_id = :event_id LIMIT 1",
            array("user_id" => $userId, "event_id" => $eventId));

        // no need to remove attendance
        if (!$attendant)
            return;

        $eventAttendantsId = $attendant["id"];
        $joinType = $attendant["type"];

        $this->database->beginTransaction();
        $this->database->exec("DELETE FROM event_attendants WHERE id = :event_attendants_id",
            array("event_attendants_id" => $eventAttendantsId));

        // update summary
        $updateColumn = false;

        if ($joinType == self::EVENT_JOIN_GOING)
            $updateColumn = "going";
        if ($joinType == self::EVENT_JOIN_MAYBE)
            $updateColumn = "maybe";
        if ($joinType == self::EVENT_JOIN_NOT_GOING)
            $updateColumn = "not_going";

        if ($updateColumn) {
            $this->database->exec("INSERT INTO event_summary(event_id, going, not_going, maybe) VALUES (:event_id, 0, 0, 0)
                ON DUPLICATE KEY UPDATE {$updateColumn} = GREATEST(0, {$updateColumn} - 1)",
                array("event_id" => $eventId)
            );
        }
        $this->database->commit();
    }

    private function addAttendant($eventId, $userId, $joinType) {
        // check if we are attending already
        $attendants = $this->database->fetchColumn("SELECT COUNT(1)
            FROM event_attendants WHERE user_id = :user_id AND event_id = :event_id",
            array("user_id" => $userId, "event_id" => $eventId));

        if ($attendants > 0)
            throw new Exception("Already attending");

        // insert
        $this->database->beginTransaction();
        $this->database->exec("INSERT INTO event_attendants(user_id, event_id, type, created) VALUES (:user_id, :event_id, :type, :created)",
            array("user_id" => $userId, "event_id" => $eventId, "type" => $joinType, "created" => time()));

        // update summary
        $updateColumn = false;

        if ($joinType == self::EVENT_JOIN_GOING)
            $updateColumn = "going";
        if ($joinType == self::EVENT_JOIN_MAYBE)
            $updateColumn = "maybe";
        if ($joinType == self::EVENT_JOIN_NOT_GOING)
            $updateColumn = "not_going";

        if ($updateColumn) {
            $this->database->exec("INSERT INTO event_summary(event_id, going, not_going, maybe) VALUES (:event_id, :going, :not_going, :maybe)
                ON DUPLICATE KEY UPDATE {$updateColumn} = {$updateColumn} + 1",
                array(
                    "event_id" => $eventId,
                    "going" => ($updateColumn == "going" ? 1 : 0),
                    "not_going" => ($updateColumn == "not_going" ? 1 : 0),
                    "maybe" => ($updateColumn == "maybe" ? 1 : 0),
                )
            );
        }
        $this->database->commit();
    }

    private function getAttendance($eventId, $userId) {
        $joinType = $this->database->fetchColumn("SELECT type
            FROM event_attendants WHERE user_id = :user_id AND event_id = :event_id LIMIT 1",
            array("user_id" => $userId, "event_id" => $eventId));

        if (!$joinType)
            return null;

        if (array_key_exists($joinType, self::$eventJoinTypes))
            return self::$eventJoinTypes[$joinType];

        return null;
    }

    private function eventIsOwner($eventId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_EVENT, $eventId);
    }

    private function eventGetGroupId($eventId) {
        return $this->objectGetGroupId(self::OBJECT_TYPE_EVENT, $eventId);
    }

    private function eventRequireMember($eventId) {
        $this->objectRequireMember(self::OBJECT_TYPE_EVENT, $eventId);
    }
}