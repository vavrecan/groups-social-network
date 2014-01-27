<?php

class Group extends LoggedUserMethodHandler
{
    const GROUP_PRIVACY_APPROVAL_NEEDED = 1;
    const GROUP_PRIVACY_ADMIN_POSTS_ONLY = 2;
    const GROUP_PRIVACY_ADMIN_ARTICLES_ONLY = 4;
    const GROUP_PRIVACY_ADMIN_EVENTS_ONLY = 8;
    const GROUP_PRIVACY_ADMIN_GALLERY_ONLY = 16;

    private static $privacyRules = array(
        self::GROUP_PRIVACY_APPROVAL_NEEDED => "approval_needed",
        self::GROUP_PRIVACY_ADMIN_POSTS_ONLY => "admin_posts_only",
        self::GROUP_PRIVACY_ADMIN_ARTICLES_ONLY => "admin_articles_only",
        self::GROUP_PRIVACY_ADMIN_EVENTS_ONLY => "admin_events_only",
        self::GROUP_PRIVACY_ADMIN_GALLERY_ONLY => "admin_galleries_only",
    );

    const GROUP_REQUEST_ADMIN = 1;
    const GROUP_REQUEST_MEMBER = 2;
    const GROUP_REQUEST_JOIN = 3;

    private static $requestTypes = array(
        self::GROUP_REQUEST_ADMIN => "request_admin",
        self::GROUP_REQUEST_MEMBER => "request_member",
        self::GROUP_REQUEST_JOIN => "request_join",
    );

    public $publicMethods = array("detail");

    public function detailHandler() {
        $this->requireParam("group_id");

        $group = $this->database->fetch("SELECT
                groups.id, groups.active, groups.created, groups.title, groups.link,
                groups.description, groups.image,
                users.id as user_id, users.name as user_name, users.image as user_image,
                groups.privacy,
                IFNULL(group_summary.members_count, 0) as members,
                IFNULL(group_summary.posts_count, 0) as posts_count,
                IFNULL(group_summary.posts_visible, 0) as posts_visible_count,
                group_locations.location_latitude,
                group_locations.location_longitude,
                group_locations.location_city,
                group_locations.location_country,
                group_locations.location_region
            FROM groups
            INNER JOIN users ON users.id = groups.user_id
            LEFT JOIN group_summary ON group_summary.group_id = groups.id
            LEFT JOIN group_locations ON group_locations.group_id = groups.id
            WHERE groups.id = :group_id AND groups.active = 1",
            array("group_id" => $this->params["group_id"]));

        if (!$group)
            throw new Exception("No such group");

        $this->formatDetail($group);

        $this->response = $group;
        $this->output();
    }

    public function createHandler() {
        $this->requireParam("title");
        $this->requireParam("description");

        $title = $this->params["title"];
        $description = $this->params["description"];
        $privacy = 0;
        $link = "";

        // check inputs
        if (empty($title) || strlen($title) < 3)
            throw new Exception("Title is too short");

        if (isset($this->params["privacy"]))
            $privacy = Utils::packData($this->params["privacy"], self::$privacyRules);

        if (isset($this->params["link"])) {
            $link = $this->params["link"];
            if (!empty($link) && !Utils::isLink($link))
                throw new Exception("Invalid link");
        }

        if (!isset($this->user["location"]["latitude"]) || empty($this->user["location"]["latitude"]))
            throw new Exception("User location is missing");

        if (!isset($this->user["location"]["longitude"]) || empty($this->user["location"]["longitude"]))
            throw new Exception("User location is missing");

        // create group
        $this->database->exec("INSERT INTO groups(title, link, description, active, created, user_id, privacy)
                                          VALUES (:title, :link, :description, :active, :created, :user_id, :privacy)",
            array(
                "title" => $title,
                "description" => $description,
                "active" => "1",
                "link" => $link,
                "created" => time(),
                "user_id" => $this->userId,
                "privacy" => $privacy
            ));

        $groupId = $this->database->lastInsertId();

        // default membership
        $this->addMember($groupId, $this->userId);
        $this->addAdmin($groupId, $this->userId);

        // tags
        if (isset($this->params["tags"]))
            $this->setTags($groupId, $this->params["tags"]);

        // copy location
        $userLocation = $this->database->fetch("SELECT
            location_latitude, location_longitude, location_country, location_region, location_city
            FROM user_locations WHERE user_id = :user_id",
            array("user_id" => $this->userId));

        if ($userLocation) {
            $this->database->exec("INSERT INTO group_locations(group_id, location_latitude, location_longitude, location_country, location_region, location_city)
                VALUES (:group_id, :location_latitude, :location_longitude, :location_country, :location_region, :location_city)",
                array(
                    "group_id" => $groupId,
                    "location_latitude" => $userLocation["location_latitude"],
                    "location_longitude" => $userLocation["location_longitude"],
                    "location_country" => $userLocation["location_country"],
                    "location_region" => $userLocation["location_region"],
                    "location_city" => $userLocation["location_city"]
                )
            );
        }

        $this->logActivity(Activity::ACTIVITY_GROUP_CREATE, $groupId, self::OBJECT_TYPE_GROUP, $groupId);
        $this->response["group_id"] = $groupId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function joinHandler() {
        $this->requireParam("group_id");
        $groupId = $this->params["group_id"];

        if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_APPROVAL_NEEDED))
            throw new Exception("Admin must add you");

        $this->addMember($groupId, $this->userId);

        $this->logActivity(Activity::ACTIVITY_GROUP_JOIN, $groupId, self::OBJECT_TYPE_GROUP, $groupId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function leaveHandler() {
        $this->requireParam("group_id");

        $groupId = $this->params["group_id"];
        $this->removeMember($groupId, $this->userId);

        $this->logActivity(Activity::ACTIVITY_GROUP_LEAVE, $groupId, self::OBJECT_TYPE_GROUP, $groupId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function removeMemberHandler() {
        $this->requireParam("group_id");
        $this->requireParam("user_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $this->removeMember($this->params["group_id"], $this->params["user_id"]);
        $this->response["success"] = 1;
        $this->output();
    }

    public function removeAdminHandler() {
        $this->requireParam("group_id");
        $this->requireParam("user_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $this->removeAdmin($this->params["group_id"], $this->params["user_id"]);
        $this->response["success"] = 1;
        $this->output();
    }

    public function updateHandler() {
        $this->requireParam("group_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $groupId = $this->params["group_id"];

        if (isset($this->params["privacy"]))
            $this->params["privacy"] = Utils::packData($this->params["privacy"], self::$privacyRules);

        if (isset($this->params["link"]) && !empty($this->params["link"]) && !Utils::isLink($this->params["link"]))
            throw new Exception("Invalid link");

        $allowedColumns = array("title", "description", "privacy", "link");

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $this->database->exec("UPDATE groups SET groups.{$column} = :value WHERE id = :group_id",
                    array("value" => $this->params[$column], "group_id" => $groupId));
            }
        }

        // tags
        if (isset($this->params["tags"]))
            $this->setTags($groupId, $this->params["tags"]);

        // return response
        $this->logActivity(Activity::ACTIVITY_GROUP_UPDATE, $groupId, self::OBJECT_TYPE_GROUP, $groupId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("group_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $groupId = $this->params["group_id"];

        $this->database->exec("UPDATE groups SET active = :active WHERE id = :group_id",
            array("active" => 0, "group_id" => $groupId));

        // TODO remove all admins
        // TODO remove all users
        // TODO remove all group summary

        $this->logActivity(Activity::ACTIVITY_GROUP_DELETE, $groupId, self::OBJECT_TYPE_GROUP, $groupId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function listHandler() {
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $type = "group_members";
        if (isset($this->params["type"])) {
            if ($this->params["type"] == "group_admins")
                $type = "group_admins";
            else if ($this->params["type"] == "group_members")
                $type = "group_members";
            else
                throw new Exception("Invalid group relationship type");
        }

        $nextPageLimit = $limit + 1;
        $hasMore = false;

        $groups = $this->database->fetchAll("SELECT
                groups.id, groups.title, groups.image,
                IFNULL(group_summary.members_count, 0) as members,
                IFNULL(group_summary.posts_count, 0) as posts_count,
                IFNULL(group_summary.posts_visible, 0) as posts_visible_count,

                IFNULL(feed_read_summary.read_count, 0) as read_count,
                IFNULL(group_summary.posts_count, 0) - IFNULL(feed_read_summary.read_count, 0) as unread_count
            FROM {$type}
            INNER JOIN groups ON (groups.id = {$type}.group_id AND groups.active = 1)
            LEFT JOIN group_summary ON group_summary.group_id = groups.id
            LEFT JOIN feed_read_summary ON (feed_read_summary.group_id = groups.id AND feed_read_summary.user_id = :user_id)
            WHERE {$type}.user_id = :user_id ORDER BY unread_count DESC,{$type}.created DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("user_id" => $this->userId));

        if (count($groups) > $limit) {
            array_pop($groups);
            $hasMore = true;
        }

        foreach($groups as &$group) {
            $group["image"] = Utils::updateImageUrl($group["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $groups;
        $this->response["type"] = $type;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listUsersHandler() {
        $this->requireParam("group_id");

        if (!$this->groupIsMember($this->params["group_id"]) && !$this->groupIsAdmin($this->params["group_id"]))
            throw new Exception("You can not view members of this group");

        $groupId = $this->params["group_id"];
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $type = "group_members";
        if (isset($this->params["type"])) {
            if ($this->params["type"] == "group_admins")
                $type = "group_admins";
            else if ($this->params["type"] == "group_members")
                $type = "group_members";
            else
                throw new Exception("Invalid group relationship type");
        }

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $users = $this->database->fetchAll("SELECT users.id, users.name, users.gender, users.image, {$type}.created as created FROM {$type}
            INNER JOIN users ON users.id = {$type}.user_id
            WHERE {$type}.group_id = :group_id" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("group_id" => $groupId));

        if (count($users) > $limit) {
            array_pop($users);
            $hasMore = true;
        }

        foreach($users as &$user) {
            $user["gender"] = User::getGenderName($user["gender"]);
            $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
            $user["created"] = Utils::updateTime($user["created"], $this->api->config["time_format"]);
        }

        $this->response["data"] = $users;
        $this->response["type"] = $type;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listGroupRequestsHandler() {
        $this->requireParam("group_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $groupId = $this->params["group_id"];
        $nextPageLimit = $limit + 1;
        $hasMore = false;

        $requests = $this->database->fetchAll("SELECT
                group_requests.id,
                group_requests.type,
                group_requests.created,

                users_from.id as user_from_id,
                users_from.name as user_from_name,
                users_from.image as user_from_image,

                users_to.id as user_to_id,
                users_to.name as user_to_name,
                users_to.image as user_to_image
            FROM group_requests
                LEFT JOIN users as users_from ON (users_from.id = group_requests.issued_user_id)
                LEFT JOIN users as users_to ON (users_to.id = group_requests.user_id)
            WHERE group_requests.group_id = :group_id ORDER BY created DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("group_id" => $groupId));

        if (count($requests) > $limit) {
            array_pop($requests);
            $hasMore = true;
        }

        foreach($requests as &$request) {
            $request["user_from"]["id"] = $request["user_from_id"];
            $request["user_from"]["image"] = Utils::updateImageUrl($request["user_from_image"], $this->api->config["images_url"]);
            $request["user_from"]["name"] = $request["user_from_name"];

            unset($request["user_from_id"]);
            unset($request["user_from_image"]);
            unset($request["user_from_name"]);

            $request["user_to"] = array();
            if ($request["user_to_id"]) {
                $request["user_to"]["id"] = $request["user_to_id"];
                $request["user_to"]["image"] = Utils::updateImageUrl($request["user_to_image"], $this->api->config["images_url"]);
                $request["user_to"]["name"] = $request["user_to_name"];
            }

            unset($request["user_to_id"]);
            unset($request["user_to_image"]);
            unset($request["user_to_name"]);

            $request["created"] = Utils::updateTime($request["created"], $this->api->config["time_format"]);
            $request["type"] = self::$requestTypes[$request["type"]];
        }

        $this->response["data"] = $requests;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listUserRequestsHandler() {
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;

        $groups = $this->database->fetchAll("SELECT
                group_requests.id,
                group_requests.type,
                group_requests.created,

                users.id as user_id, users.name as user_name, users.image as user_image,
                groups.id as group_id, groups.title as group_title, groups.image as group_image,

                IFNULL(group_summary.members_count, 0) as members,
                IFNULL(group_summary.posts_count, 0) as posts_count,
                IFNULL(group_summary.posts_visible, 0) as posts_visible_count
            FROM group_requests
                INNER JOIN users ON (users.id = group_requests.issued_user_id)
                INNER JOIN groups ON (groups.id = group_requests.group_id AND groups.active = 1)
                LEFT JOIN group_summary ON group_summary.group_id = groups.id
            WHERE group_requests.user_id = :user_id ORDER BY created DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("user_id" => $this->userId));

        if (count($groups) > $limit) {
            array_pop($groups);
            $hasMore = true;
        }

        foreach($groups as &$group) {
            $group["user"]["id"] = $group["user_id"];
            $group["user"]["image"] = Utils::updateImageUrl($group["user_image"], $this->api->config["images_url"]);
            $group["user"]["name"] = $group["user_name"];

            unset($group["user_id"]);
            unset($group["user_image"]);
            unset($group["user_name"]);

            $group["group"]["id"] = $group["group_id"];
            $group["group"]["image"] = Utils::updateImageUrl($group["group_image"], $this->api->config["images_url"]);
            $group["group"]["title"] = $group["group_title"];

            unset($group["group_id"]);
            unset($group["group_image"]);
            unset($group["group_title"]);

            $group["created"] = Utils::updateTime($group["created"], $this->api->config["time_format"]);
            $group["type"] = self::$requestTypes[$group["type"]];
        }

        $this->response["data"] = $groups;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function acceptRequestHandler() {
        $this->requireParam("request_id");
        $requestId = $this->params["request_id"];

        $request = $this->database->fetch("SELECT group_id, user_id, type, issued_user_id FROM group_requests WHERE id = :request_id",
            array("request_id" => $requestId));

        if (!$request)
            throw new Exception("No such request");

        $issuedUserId = $request["issued_user_id"];
        $userId = $request["user_id"];
        $groupId = $request["group_id"];
        $typeId = $request["type"];

        // check owner for GROUP_REQUEST_ADMIN and GROUP_REQUEST_MEMBER
        if ($typeId != self::GROUP_REQUEST_JOIN && $this->userId != $request["user_id"])
            throw new Exception("This request is for another user");

        if ($typeId == self::GROUP_REQUEST_JOIN) {
            // require admin to confirm
            $this->groupRequireAdmin($groupId);
            $this->addMember($groupId, $issuedUserId);

            // create notification that admin user confirmed join request
            $this->notificationCreate($issuedUserId, $this->userId, $groupId, Notification::NOTIFICATION_MEMBER);
        }

        if ($typeId == self::GROUP_REQUEST_ADMIN) {
            $this->addAdmin($groupId, $userId);
        }

        if ($typeId == self::GROUP_REQUEST_MEMBER) {
            $this->addMember($groupId, $userId);
        }

        // remove this request
        $this->database->exec("DELETE FROM group_requests WHERE id = :request_id",
            array("request_id" => $requestId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function cancelRequestHandler() {
        $this->requireParam("request_id");
        $requestId = $this->params["request_id"];
        $request = $this->database->fetch("SELECT group_id, user_id, type FROM group_requests WHERE id = :request_id",
            array("request_id" => $requestId));

        if (!$request)
            throw new Exception("No such request");

        // either user who own request can accept or group admin
        if ($this->userId != $request["user_id"] && !$this->groupIsAdmin($request["group_id"])) {
            throw new Exception("This request is for another user");
        }

        $this->database->exec("DELETE FROM group_requests WHERE id = :request_id",
            array("request_id" => $requestId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function createRequestHandler() {
        $this->requireParam("group_id");
        $this->requireParam("type");

        $userId = isset($this->params["user_id"]) ? $this->params["user_id"] : 0;
        $groupId = $this->params["group_id"];
        $typeId = array_search($this->params["type"], self::$requestTypes);

        if ($typeId == self::GROUP_REQUEST_ADMIN) {
            $this->requireParam("user_id");

            if ($this->isAdmin($groupId, $userId))
                throw new Exception("User is already admin");

            $this->groupRequireAdmin($this->params["group_id"]);
        }

        if ($typeId == self::GROUP_REQUEST_MEMBER) {
            $this->requireParam("user_id");

            if ($this->isMember($groupId, $userId))
                throw new Exception("User is already member");

            // check if only admin can add member privacy is set
            if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_APPROVAL_NEEDED)) {
                $this->groupRequireAdmin($this->params["group_id"]);
            }
            else {
                // require member only if not admin
                if (!$this->groupIsAdmin($this->userId))
                    $this->groupRequireMember($this->params["group_id"]);
            }
        }

        if ($typeId == self::GROUP_REQUEST_JOIN) {
            if ($this->isMember($groupId, $this->userId))
                throw new Exception("You are already a member");
        }

        $this->database->exec("INSERT INTO group_requests
                (group_id, user_id, type, created, issued_user_id)
            VALUES
                (:group_id, :user_id, :type_id, :created, :issued_user_id)
            ON DUPLICATE KEY UPDATE
                created = :created, issued_user_id = :issued_user_id",
            array(
                "group_id" => $groupId,
                "user_id" => $userId,
                "type_id" => $typeId,
                "created" => time(),
                "issued_user_id" => $this->userId,
            )
        );

        // create notification if there is target user
        if ($userId)
            $this->notificationCreate($userId, $this->userId, $groupId, Notification::NOTIFICATION_JOIN_REQUEST);

        $this->response["success"] = 1;
        $this->output();
    }

    public function updateLocationHandler() {
        $this->requireParam("group_id");
        $this->requireParam("latitude");
        $this->requireParam("longitude");
        $this->groupRequireAdmin($this->params["group_id"]);

        $groupId = $this->params["group_id"];
        $latitude = (float)$this->params["latitude"];
        $longitude = (float)$this->params["longitude"];

        $geoInfo = Utils::reverseGeolocation($this->database, $latitude, $longitude);

        $this->database->exec("INSERT INTO group_locations
                (group_id, location_latitude, location_longitude, location_country, location_region, location_city)
            VALUES
                (:group_id, :location_latitude, :location_longitude, :location_country_id, :location_region_id, :location_city_id)
            ON DUPLICATE KEY UPDATE
                location_latitude = :location_latitude,
                location_longitude = :location_longitude,
                location_country = :location_country_id,
                location_region = :location_region_id,
                location_city = :location_city_id",
            array(
                "group_id" => $groupId,
                "location_latitude" => $latitude,
                "location_longitude" => $longitude,
                "location_country_id" => $geoInfo["country_id"],
                "location_region_id" => $geoInfo["region_id"],
                "location_city_id" => $geoInfo["city_id"],
            )
        );

        $this->response["success"] = 1;
        $this->output();
    }

    public function setImageHandler() {
        $this->requireParam("group_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $groupId = $this->params["group_id"];

        if (!isset($_FILES["image"]))
            throw new Exception("Image file upload missing");

        $uploadPath = $this->api->config["images_upload_path"];
        $fileName = Utils::saveImage($_FILES["image"]["tmp_name"], $uploadPath, self::IMAGE_ID_GROUP . $groupId);

        // check if there was image set before
        $previousImage = $this->database->fetchColumn("SELECT image FROM groups WHERE id = :group_id",
            array("group_id" => $groupId));

        if (!empty($previousImage))
            Utils::deleteImage($uploadPath, $previousImage);

        // save new image
        $this->database->exec("UPDATE groups SET image = :image WHERE id = :group_id",
            array("group_id" => $groupId, "image" => $fileName));

        $this->response["image"] = $this->api->config["images_url"] . $fileName;
        $this->response["success"] = 1;
        $this->output();
    }

    public function removeImageHandler() {
        $this->requireParam("group_id");
        $this->groupRequireAdmin($this->params["group_id"]);

        $groupId = $this->params["group_id"];

        $uploadPath = $this->api->config["images_upload_path"];

        $previousImage = $this->database->fetchColumn("SELECT image FROM groups WHERE id = :group_id",
            array("group_id" => $groupId));

        if (!empty($previousImage)) {
            Utils::deleteImage($uploadPath, $previousImage);

            $this->database->exec("UPDATE groups SET image = :image WHERE id = :group_id",
                array("group_id" => $groupId, "image" => ""));
        }

        $this->response["success"] = 1;
        $this->output();
    }

    public function searchHandler() {
        $this->requireParam("title");
        $this->requireParam("tags");
        $this->requireParam("min_members");
        $this->requireParam("distance");

        if (empty($this->params["distance"])) {
            $this->requireParam("country_id");
            $this->requireParam("region_id");
            $this->requireParam("city_id");

            if (empty($this->params["country_id"]))
                throw new Exception("Country must be specified to proceed with search");
        }

        $minMembers = (int)$this->params["min_members"];
        $distance = (float)$this->params["distance"];
        $title = $this->params["title"];
        $tags = $this->params["tags"];

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;

        /*
         * Hacky way to check if group has required tags, but performance should not suffer here
         * -- or ! -- another way to check tag
         * INNER JOIN group_tags a ON a.group_id = groups.id AND a.tag_id = 6
         * INNER JOIN group_tags b ON b.group_id = groups.id AND b.tag_id = 7
         */
        $tagsMatch = "";
        if (!empty($tags)) {
            $tagIds = $this->getTagIds($tags);

            if (count($tagIds) > 0)
                $tagsMatch = "AND
                        (SELECT COUNT(1)
                            FROM group_tags
                            WHERE group_tags.group_id = groups.id
                                AND tag_id IN(" . join(",", $tagIds) . ")
                        ) = " . count($tagIds);
        }

        if (!empty($distance)) {
            // search by distance
            if ($distance > 500)
                throw new Exception("Distance too big");

            if (!isset($this->user["location"]["latitude"]) || empty($this->user["location"]["latitude"]))
                throw new Exception("User location is missing");

            if (!isset($this->user["location"]["longitude"]) || empty($this->user["location"]["longitude"]))
                throw new Exception("User location is missing");

            $latitude = $this->user["location"]["latitude"];
            $longitude = $this->user["location"]["longitude"];

            // search area
            $longitudeFrom = (float)$longitude - (float)$distance / abs(cos(deg2rad($latitude))*69.0);
            $longitudeTo = (float)$longitude + (float)$distance / abs(cos(deg2rad($latitude))*69.0);

            $latitudeFrom = (float)$latitude - ((float)$distance/69.0);
            $latitudeTo = (float)$latitude + ((float)$distance/69.0);

            $groups = $this->database->fetchAll("SELECT
                    groups.id, groups.image, groups.title,
                    3956 * 2 * ASIN(SQRT(POWER(SIN((:lookup_lat - (group_locations.location_latitude)) *
                    PI()/180 / 2),2) +
                    COS(:lookup_lat * PI()/180) * COS((group_locations.location_latitude) * PI()/180) *
                    POWER(SIN((:lookup_long - group_locations.location_longitude) * PI()/180 / 2),2))) as distance,
                    group_locations.location_latitude,
                    group_locations.location_longitude,
                    IFNULL(group_summary.members_count, 0) as members,
                    IFNULL(group_summary.posts_count, 0) as posts_count,
                    IFNULL(group_summary.posts_visible, 0) as posts_visible_count
                FROM group_locations
                INNER JOIN groups ON groups.id = group_locations.group_id
                LEFT JOIN group_summary ON group_summary.group_id = groups.id
                WHERE
                    group_locations.location_latitude BETWEEN :lat_from AND :lat_to
                    AND group_locations.location_longitude BETWEEN :long_from AND :long_to
                    AND group_locations.location_latitude <> 0 AND group_locations.location_longitude <> 0

                    AND groups.active = 1
                    AND group_summary.members_count >= :min_members
                    AND IF(:title = '', 1, IF(groups.title LIKE CONCAT('%', :title, '%'), 1, 0)) = 1
                    " . $tagsMatch . "
                ORDER BY distance ASC
                LIMIT ".$nextPageLimit." OFFSET ".$offset,
                array(
                    "lookup_lat" => $latitude,
                    "lookup_long" => $longitude,
                    "long_from" => $longitudeFrom,
                    "long_to" => $longitudeTo,
                    "lat_from" => $latitudeFrom,
                    "lat_to" => $latitudeTo,
                    "min_members" => $minMembers,
                    "title" => $title
                )
            );
        }
        else {
            // search by know location
            $countryId = (int)$this->params["country_id"];
            $regionId = (int)$this->params["region_id"];
            $cityId = (int)$this->params["city_id"];

            $groups = $this->database->fetchAll("SELECT
                    groups.id, groups.image, groups.title, 0 as distance,
                    group_locations.location_latitude,
                    group_locations.location_longitude,
                    IFNULL(group_summary.members_count, 0) as members,
                    IFNULL(group_summary.posts_count, 0) as posts_count,
                    IFNULL(group_summary.posts_visible, 0) as posts_visible_count
                FROM group_locations
                INNER JOIN groups ON groups.id = group_locations.group_id
                LEFT JOIN group_summary ON group_summary.group_id = groups.id
                WHERE
                    group_locations.location_country = :country_id
                    AND IF(:region_id = 0, 1, IF(group_locations.location_region = :region_id, 1, 0)) = 1
                    AND IF(:city_id = 0, 1, IF(group_locations.location_city = :city_id, 1, 0)) = 1

                    AND groups.active = 1
                    AND group_summary.members_count >= :min_members
                    AND IF(:title = '', 1, IF(groups.title LIKE CONCAT('%', :title, '%'), 1, 0)) = 1
                    " . $tagsMatch . "

                LIMIT ".$nextPageLimit." OFFSET ".$offset,
                array(
                    "country_id" => $countryId,
                    "region_id" => $regionId,
                    "city_id" => $cityId,
                    "min_members" => $minMembers,
                    "title" => $title
                )
            );
        }

        if (count($groups) > $limit) {
            array_pop($groups);
            $hasMore = true;
        }

        foreach($groups as &$group) {
            Utils::translateLocation($this->database, $group);
            $group["image"] = Utils::updateImageUrl($group["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $groups;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function searchTagsHandler() {
        $this->requireParam("name");
        $name = $this->params["name"];

        $tags = $this->database->fetchAll("SELECT id, name FROM tags WHERE name LIKE :name LIMIT 20",
            array("name" => $name . "%"));

        $this->response["data"] = $tags;
        $this->output();
    }

    public function reportHandler() {
        $this->requireParam("group_id");
        $this->requireParam("message");

        $this->database->exec("INSERT INTO group_reports(group_id, message, user_id, created)
            VALUES(:group_id, :message, :user_id, :created)",
            array(
                "group_id" => $this->params["group_id"],
                "message" => $this->params["message"],
                "user_id" => $this->userId,
                "created" => time()
            )
        );

        $this->response["success"] = 1;
        $this->output();
    }

    private function getTagIds($tags) {
        $tags = json_decode($tags, true);

        if (!is_array($tags))
            return array();

        $tagIds = array();

        foreach ($tags as $tag) {
            $tagName = trim($tag);
            $tagName = mb_strtolower($tagName, 'UTF-8');

            $tagId = $this->database->fetchColumn("SELECT id FROM tags WHERE name = :tag", array("tag" => $tagName));

            if (!$tagId)
                $tagId = -1;

            array_push($tagIds, $tagId);
        }

        return $tagIds;
    }

    private function setTags($groupId, $tags) {
        $tags = json_decode($tags, true);

        if (!is_array($tags))
            return;

        $tagIds = array();

        foreach ($tags as $tag) {
            $tagName = trim($tag);
            $tagName = mb_strtolower($tagName, 'UTF-8');

            $tagId = $this->database->fetchColumn("SELECT id FROM tags WHERE name = :tag",
                array("tag" => $tagName)
            );

            if (!$tagId) {
                $this->database->exec("INSERT INTO tags (name, user_id) VALUES(:tag, :user_id)",
                    array("tag" => $tagName, "user_id" => $this->userId)
                );

                $tagId = $this->database->lastInsertId();
            }

            array_push($tagIds, $tagId);
        }

        $groupTagIds = $this->database->fetchAllColumn("SELECT tag_id FROM group_tags WHERE group_id = :group_id",
            array("group_id" => $groupId)
        );

        // get difference
        $toRemove = array_diff($groupTagIds, $tagIds);
        $toAdd = array_diff($tagIds, $groupTagIds);

        foreach ($toRemove as $tagId)
            $this->database->exec("DELETE FROM group_tags WHERE group_id = :group_id AND tag_id = :tag_id",
                array("group_id" => $groupId, "tag_id" => $tagId));

        foreach ($toAdd as $tagId)
            $this->database->exec("INSERT INTO group_tags(group_id, tag_id) VALUES(:group_id, :tag_id)",
                array("group_id" => $groupId, "tag_id" => $tagId));
    }

    private function getTags($groupId) {
        $tags = $this->database->fetchAllColumn("SELECT tags.name
            FROM group_tags
            INNER JOIN tags ON tags.id = group_tags.tag_id
            WHERE group_id = :group_id",
            array("group_id" => $groupId));

        return $tags;
    }

    private function isMember($groupId, $userId) {
        $memeberships = $this->database->fetchColumn("SELECT COUNT(1)
            FROM group_members WHERE user_id = :user_id AND group_id = :group_id",
            array("user_id" => $userId, "group_id" => $groupId));

        if ($memeberships > 0)
            return true;

        return false;
    }

    private function addMember($groupId, $userId) {
        if ($this->isMember($groupId, $userId))
            throw new Exception("Already member of this group");

        // insert
        $this->database->beginTransaction();

        $this->database->exec("INSERT INTO group_members(user_id, group_id, created) VALUES (:user_id, :group_id, :created)",
            array("user_id" => $userId, "group_id" => $groupId, "created" => time()));

        // update summary
        $this->database->exec("INSERT INTO group_summary(group_id, members_count, posts_count, posts_visible) VALUES (:group_id, 1, 0, 0)
            ON DUPLICATE KEY UPDATE members_count = members_count + 1",
            array("group_id" => $groupId));

        $this->database->commit();
    }

    private function removeMember($groupId, $userId) {
        $this->database->beginTransaction();

        $rowCount = $this->database->exec("DELETE FROM group_members WHERE user_id = :user_id AND group_id = :group_id",
            array("user_id" => $userId, "group_id" => $groupId), true);

        if ($rowCount > 0)
        {
            // update summary if something was deleted
            $this->database->exec("INSERT INTO group_summary(group_id, members_count, posts_count, posts_visible) VALUES (:group_id, 0, 0, 0)
                ON DUPLICATE KEY UPDATE members_count = GREATEST(0, members_count - 1)",
                array("group_id" => $groupId));
        }

        $this->database->commit();
    }

    private function isAdmin($groupId, $userId) {
        $adminships = $this->database->fetchColumn("SELECT COUNT(1) FROM group_admins WHERE user_id = :user_id AND group_id = :group_id",
            array("user_id" => $userId, "group_id" => $groupId));

        if ($adminships > 0)
            return true;

        return false;
    }

    private function addAdmin($groupId, $userId) {
        if ($this->isAdmin($groupId, $userId))
            throw new Exception("Already admin of this group");

        // insert
        $this->database->exec("INSERT INTO group_admins(user_id, group_id, created) VALUES (:user_id, :group_id, :created)",
            array("user_id" => $userId, "group_id" => $groupId, "created" => time()));
    }

    private function removeAdmin($groupId, $userId) {
        $this->database->exec("DELETE FROM group_admins WHERE user_id = :user_id AND group_id = :group_id",
            array("user_id" => $userId, "group_id" => $groupId));
    }


    private function formatDetail(&$group) {
        $group["user"]["id"] = $group["user_id"];
        $group["user"]["image"] = Utils::updateImageUrl($group["user_image"], $this->api->config["images_url"]);
        $group["user"]["name"] = $group["user_name"];

        unset($group["user_id"]);
        unset($group["user_image"]);
        unset($group["user_name"]);

        Utils::translateLocation($this->database, $group);

        if ($this->user) {
            $group["can_edit"] = $this->groupIsAdmin($group["id"]);
            $group["is_member"] = $this->groupIsMember($group["id"]);
            $group["distance"] = Utils::calculateDistance($this->user["location"], $group["location"]);
            $group["can_interact"] = true;
        }
        else {
            $group["can_interact"] = false;
        }

        $group["tags"] = $this->getTags($group["id"]);
        $group["privacy"] = Utils::unpackData($group["privacy"], self::$privacyRules);
        $group["image"] = Utils::updateImageUrl($group["image"], $this->api->config["images_url"]);
        $group["created"] = Utils::updateTime($group["created"], $this->api->config["time_format"]);
    }
}