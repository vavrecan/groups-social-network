<?php

class User extends LoggedUserMethodHandler
{
    const USER_PRIVACY_SHOW_ON_MAP = 1;

    const USER_GENDER_MALE = 1;
    const USER_GENDER_FEMALE = 2;

    public static $genders = array(
        self::USER_GENDER_MALE => "male",
        self::USER_GENDER_FEMALE => "female"
    );

    public static function getGenderName($id) {
        if (isset(self::$genders[$id]))
            return self::$genders[$id];

        throw new Exception("Invalid gender id: {$id}");
    }

    public static function getGenderId($name) {
        $genderId = array_search($name, self::$genders);

        if ($genderId)
            return $genderId;

        if (empty($name))
            throw new Exception("Gender not specified");

        throw new Exception("Invalid gender name: {$name}");
    }

    public $publicMethods = array("detail", "listMembership");

    public static function updateLocationByIp($database, $userId) {
        // only update if no record exists
        if ($database->fetchColumn("SELECT COUNT(1) FROM user_locations WHERE user_id = :user_id",
            array("user_id" => $userId)) > 0)
            return;

        // update country, region, city
        $ip = $_SERVER["REMOTE_ADDR"];

        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];

        $ipData = Utils::geoipGetRecord($ip);

        $countryCode = isset($ipData["country_code"]) ? $ipData["country_code"] : "";
        $regionCode = isset($ipData["region"]) ? $ipData["region"] : "";
        $cityName = isset($ipData["city"]) ? $ipData["city"] : "";

        // check if country name, city name and region name exists in our geonames table
        $geoInfo = Utils::resolveLocation($database, $countryCode, $regionCode, $cityName);

        $latitude = isset($ipData['latitude']) ? $ipData['latitude'] : 0;
        $longitude = isset($ipData['longitude']) ? $ipData['longitude'] : 0;

        // only insert location if nothing was set before
        $database->exec("INSERT INTO user_locations
                (user_id, location_latitude, location_longitude, location_country, location_region, location_city)
            VALUES
                (:user_id, :location_latitude, :location_longitude, :location_country_id, :location_region_id, :location_city_id)",
            array(
                "user_id" => $userId,
                "location_latitude" => $latitude,
                "location_longitude" => $longitude,
                "location_country_id" => $geoInfo["country_id"],
                "location_region_id" => $geoInfo["region_id"],
                "location_city_id" => $geoInfo["city_id"],
            )
        );
    }

    public function meHandler() {
        $this->response = $this->user;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("user_id");

        $user = $this->database->fetch("SELECT
                users.id,
                users.active,
                users.verified,
                users.name,
                users.gender,
                users.image,
                users.birthday,
                users.created,
                IFNULL(user_summary.followers, 0) as followers,
                IFNULL(user_summary.following, 0) as following,
                user_locations.location_latitude,
                user_locations.location_longitude,
                user_locations.location_city,
                user_locations.location_country,
                user_locations.location_region
            FROM users
            LEFT JOIN user_locations ON user_locations.user_id = users.id
            LEFT JOIN user_summary ON user_summary.user_id = users.id
            WHERE users.id = :user_id AND users.active = 1",
            array("user_id" => $this->params["user_id"]));

        if (!$user)
            throw new Exception("No such user");

        Utils::translateLocation($this->database, $user);

        if ($this->user) {
            $user["is_following"] = $this->database->fetchColumn("SELECT COUNT(1) FROM user_follow
                WHERE user_follow.user_id = :user_id AND user_follow.follow_user_id = :current_user_id",
                array("user_id" => $this->params["user_id"], "current_user_id" => $this->userId));

            $user["is_followed"] = $this->database->fetchColumn("SELECT COUNT(1) FROM user_follow
                WHERE user_follow.user_id = :current_user_id AND user_follow.follow_user_id = :user_id",
                array("user_id" => $this->params["user_id"], "current_user_id" => $this->userId));

            $user["distance"] = Utils::calculateDistance($this->user["location"], $user["location"]);
        }

        $user["gender"] = self::getGenderName($user["gender"]);
        $user["birthday"] = Utils::fromBirthday($user["birthday"]);
        $user["age"] = Utils::getAge($user["birthday"]);
        $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
        $user["created"] = Utils::updateTime($user["created"], $this->api->config["time_format"]);

        $this->response = $user;
        $this->output();
    }

    public function changePasswordHandler() {
        $this->requireParam("password");

        $password = $this->params["password"];
        if (strlen($password) < 6)
            throw new Exception("Password is too short");

        $passwordHash = hash("sha512", $password);

        $this->database->exec("UPDATE users SET password = UNHEX(:password) WHERE id = :user_id",
            array("user_id" => $this->userId, "password" => $passwordHash));

        // return response
        $this->response["success"] = 1;
        $this->output();
    }

    public function updateHandler() {
        $allowedColumns = array("first_name", "last_name", "birthday", "gender");

        if (isset($this->params["birthday"])) {
            $this->params["birthday"] = Utils::getBirthday($this->params["birthday"]);
        }

        if (isset($this->params["gender"])) {
            $this->params["gender"] = self::getGenderId($this->params["gender"]);
        }

        if (isset($this->params["first_name"]) && strlen(trim($this->params["first_name"])) == 0)
            throw new Exception("First name can not be empty");

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $this->database->exec("UPDATE users SET users.{$column} = :value WHERE id = :user_id",
                    array("value" => $this->params[$column], "user_id" => $this->userId));
            }
        }

        if (isset($this->params["first_name"]) || isset($this->params["last_name"])) {
            $this->database->exec("UPDATE users SET users.name = CONCAT(users.first_name, ' ', users.last_name) WHERE id = :user_id",
                array("user_id" => $this->userId));
        }

        // TODO privacy

        $this->logActivity(Activity::ACTIVITY_USER_UPDATE, 0, self::OBJECT_TYPE_USER, $this->userId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function followHandler() {
        $this->requireParam("user_id");

        $followId = $this->params["user_id"];

        // check if we are following this user
        $following = $this->database->fetchColumn("SELECT COUNT(1) FROM user_follow WHERE user_id = :user_id AND follow_user_id = :follow_user_id",
            array("user_id" => $this->userId, "follow_user_id" => $followId));

        if ($following > 0)
            throw new Exception("Already following specified user");

        // check if user is valid
        $user = $this->database->fetchColumn("SELECT active FROM users WHERE id = :follow_user_id",
            array("follow_user_id" => $followId));

        if ($user != 1)
            throw new Exception("Invalid or inactive user");

        $this->database->beginTransaction();

        // follow
        $this->database->exec("INSERT INTO user_follow (user_id, follow_user_id, created) VALUES(:user_id, :follow_user_id, :created)",
            array("user_id" => $this->userId, "follow_user_id" => $followId, "created" => time()));

        // update summary
        $this->database->exec("INSERT INTO user_summary(user_id, following, followers) VALUES (:user_id, 1, 0)
            ON DUPLICATE KEY UPDATE following = following + 1",
            array("user_id" => $this->userId));

        $this->database->exec("INSERT INTO user_summary(user_id, following, followers) VALUES (:user_id, 0, 1)
            ON DUPLICATE KEY UPDATE followers = followers + 1",
            array("user_id" => $followId));

        $this->database->commit();

        // return response
        $this->logActivity(Activity::ACTIVITY_USER_FOLLOW, 0, self::OBJECT_TYPE_USER, $followId);
        $this->notificationCreate($followId, $this->userId, 0, Notification::NOTIFICATION_FOLLOW, self::OBJECT_TYPE_USER, $followId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function unfollowHandler() {
        $this->requireParam("user_id");

        $followId = $this->params["user_id"];

        $this->database->beginTransaction();

        $rowCount = $this->database->exec("DELETE FROM user_follow WHERE user_id = :user_id AND follow_user_id = :follow_user_id",
            array("user_id" => $this->userId, "follow_user_id" => $followId), true);

        if ($rowCount > 0) {
            // update summary
            $this->database->exec("INSERT INTO user_summary(user_id, following, followers) VALUES (:user_id, 0, 0)
                ON DUPLICATE KEY UPDATE following = GREATEST(0, following - 1)",
                array("user_id" => $this->userId));

            $this->database->exec("INSERT INTO user_summary(user_id, following, followers) VALUES (:user_id, 0, 0)
                ON DUPLICATE KEY UPDATE followers = GREATEST(0, followers - 1)",
                array("user_id" => $followId));
        }

        $this->database->commit();

        // return response
        $this->logActivity(Activity::ACTIVITY_USER_UNFOLLOW, 0, self::OBJECT_TYPE_USER, $followId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function listFollowersHandler() {
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $users = $this->database->fetchAll("SELECT
                users.id, users.name, users.gender, users.image,
                (SELECT COUNT(1) FROM user_follow b WHERE b.user_id = :follow_user_id AND b.follow_user_id = user_follow.user_id) as is_followed
            FROM user_follow
            INNER JOIN users ON users.id = user_follow.user_id
            WHERE follow_user_id = :follow_user_id" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("follow_user_id" => $this->userId));

        if (count($users) > $limit) {
            array_pop($users);
            $hasMore = true;
        }

        foreach($users as &$user) {
            $user["gender"] = self::getGenderName($user["gender"]);
            $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $users;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listFollowingHandler() {
        $limit = 10;
        $offset = 0;
        $name = "";

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        if (isset($this->params["name"]))
            $name = $this->params["name"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $users = $this->database->fetchAll("SELECT
                users.id, users.name, users.gender, users.image,
                1 as is_followed
            FROM user_follow
            INNER JOIN users ON users.id = user_follow.follow_user_id
            WHERE
                user_id = :user_id AND
                IF(:name = '', 1, IF(users.name LIKE CONCAT('%', :name, '%'), 1, 0)) = 1 " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("user_id" => $this->userId, "name" => $name));

        if (count($users) > $limit) {
            array_pop($users);
            $hasMore = true;
        }

        foreach($users as &$user) {
            $user["gender"] = self::getGenderName($user["gender"]);
            $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $users;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function updateLocationHandler() {
        $this->requireParam("longitude");
        $this->requireParam("latitude");

        $latitude = (float)$this->params["latitude"];
        $longitude = (float)$this->params["longitude"];

        $geoInfo = Utils::reverseGeolocation($this->database, $latitude, $longitude);

        $this->database->exec("INSERT INTO user_locations
                (user_id, location_latitude, location_longitude, location_country, location_region, location_city)
            VALUES
                (:user_id, :location_latitude, :location_longitude, :location_country_id, :location_region_id, :location_city_id)
            ON DUPLICATE KEY UPDATE
                location_latitude = :location_latitude,
                location_longitude = :location_longitude,
                location_country = :location_country_id,
                location_region = :location_region_id,
                location_city = :location_city_id",
            array(
                "user_id" => $this->userId,
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
        if (!isset($_FILES["image"]))
            throw new Exception("Image file upload missing");

        $uploadPath = $this->api->config["images_upload_path"];
        $fileName = Utils::saveImage($_FILES["image"]["tmp_name"], $uploadPath, self::IMAGE_ID_USER . $this->userId);

        // check if there was image set before
        $previousImage = $this->database->fetchColumn("SELECT image FROM users WHERE id = :user_id",
            array("user_id" => $this->userId));

        if (!empty($previousImage))
            Utils::deleteImage($uploadPath, $previousImage);

        // save new image
        $this->database->exec("UPDATE users SET image = :image WHERE id = :user_id",
            array("user_id" => $this->userId, "image" => $fileName));

        $this->response["image"] = $this->api->config["images_url"] . $fileName;
        $this->response["success"] = 1;
        $this->output();
    }

    public function removeImageHandler() {
        $uploadPath = $this->api->config["images_upload_path"];

        $previousImage = $this->database->fetchColumn("SELECT image FROM users WHERE id = :user_id",
            array("user_id" => $this->userId));

        if (!empty($previousImage)) {
            Utils::deleteImage($uploadPath, $previousImage);

            $this->database->exec("UPDATE users SET image = :image WHERE id = :user_id",
                array("user_id" => $this->userId, "image" => ""));
        }

        $this->response["success"] = 1;
        $this->output();
    }

    public function reportHandler() {
        $this->requireParam("user_id");
        $this->requireParam("message");

        $typeId = 0;
        $objectId = 0;

        if (isset($this->params["type"]))
            $typeId = $this->getObjectTypeId($this->params["type"]);

        if (isset($this->params["object_id"]))
            $objectId = $this->params["object_id"];

        $this->database->exec("INSERT INTO user_reports(reported_user_id, message, user_id, created, type_id, object_id)
            VALUES(:report_user_id, :message, :user_id, :created, :type_id, :object_id)",
            array(
                "report_user_id" => $this->params["user_id"],
                "message" => $this->params["message"],
                "user_id" => $this->userId,
                "created" => time(),
                "type_id" => $typeId,
                "object_id" => $objectId
            )
        );

        $this->response["success"] = 1;
        $this->output();
    }

    public function getUnreadCountHandler() {
        $unreadConversations = $this->database->fetchColumn("SELECT
                COUNT(1)
            FROM message_conversation_users
                INNER JOIN message_conversations ON (message_conversation_users.conversation_id = message_conversations.id)
            WHERE
                message_conversation_users.user_id = :user_id AND
                message_conversations.last_message_id <> IFNULL(message_conversation_users.last_read_message_id, 0)
            LIMIT 10",
            array(
                "user_id" => $this->userId
            )
        );

        $unreadNotifications = (int)$this->database->fetchColumn("SELECT notification_count - read_count FROM notification_summary WHERE user_id = :user_id",
            array("user_id" => $this->userId));

        $this->response["unread_conversations"] = $unreadConversations;
        $this->response["unread_notifications"] = $unreadNotifications;
        $this->output();
    }

    public function listMembershipHandler() {
        $this->requireParam("user_id");

        $userId = $this->params["user_id"];

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $type = "group_members";
        $nextPageLimit = $limit + 1;
        $hasMore = false;

        $groups = $this->database->fetchAll("SELECT
                groups.id, groups.title, groups.image,
                IFNULL(group_summary.members_count, 0) as members
            FROM group_members
            INNER JOIN groups ON (groups.id = group_members.group_id AND groups.active = 1)
            LEFT JOIN group_summary ON group_summary.group_id = groups.id
            WHERE group_members.user_id = :user_id ORDER BY group_members.created DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array("user_id" => $userId));

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

    public function searchEmailHandler() {
        $this->requireParam("email");
        $user = $this->database->fetch("SELECT
                users.id,
                users.name,
                users.gender,
                users.image
            FROM users
            WHERE users.email = :email",
            array("email" => $this->params["email"]));

        if (!$user)
            throw new Exception("User with this email do not exists");

        $user["gender"] = self::getGenderName($user["gender"]);
        $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);

        $this->response = $user;
        $this->output();
    }

    public function searchHandler() {
        $this->requireParam("name");
        $this->requireParam("distance");

        if (empty($this->params["distance"])) {
            $this->requireParam("country_id");
            $this->requireParam("region_id");
            $this->requireParam("city_id");

            if (empty($this->params["country_id"]))
                throw new Exception("Country must be specified to proceed with search");
        }

        $distance = (float)$this->params["distance"];
        $name = $this->params["name"];

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;

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

            $users = $this->database->fetchAll("SELECT
                    users.id, users.image, users.name,
                    3956 * 2 * ASIN(SQRT(POWER(SIN((:lookup_lat - (user_locations.location_latitude)) *
                    PI()/180 / 2),2) +
                    COS(:lookup_lat * PI()/180) * COS((user_locations.location_latitude) * PI()/180) *
                    POWER(SIN((:lookup_long - user_locations.location_longitude) * PI()/180 / 2),2))) as distance,
                    user_locations.location_latitude,
                    user_locations.location_longitude
                FROM user_locations
                INNER JOIN users ON users.id = user_locations.user_id
                WHERE
                    user_locations.location_latitude BETWEEN :lat_from AND :lat_to
                    AND user_locations.location_longitude BETWEEN :long_from AND :long_to
                    AND user_locations.location_latitude <> 0 AND user_locations.location_longitude <> 0

                    AND users.id <> :user_id
                    AND users.active = 1
                    AND IF(:name = '', 1, IF(users.name LIKE CONCAT('%', :name, '%'), 1, 0)) = 1
                ORDER BY distance ASC
                LIMIT ".$nextPageLimit." OFFSET ".$offset,
                array(
                    "lookup_lat" => $latitude,
                    "lookup_long" => $longitude,
                    "long_from" => $longitudeFrom,
                    "long_to" => $longitudeTo,
                    "lat_from" => $latitudeFrom,
                    "lat_to" => $latitudeTo,
                    "name" => $name,
                    "user_id" => $this->userId
                )
            );
        }
        else {
            // search by know location
            $countryId = (int)$this->params["country_id"];
            $regionId = (int)$this->params["region_id"];
            $cityId = (int)$this->params["city_id"];

            $users = $this->database->fetchAll("SELECT
                    users.id, users.image, users.name, 0 as distance,
                    user_locations.location_latitude,
                    user_locations.location_longitude
                FROM user_locations
                INNER JOIN users ON users.id = user_locations.user_id
                WHERE
                    user_locations.location_country = :country_id
                    AND IF(:region_id = 0, 1, IF(user_locations.location_region = :region_id, 1, 0)) = 1
                    AND IF(:city_id = 0, 1, IF(user_locations.location_city = :city_id, 1, 0)) = 1

                    AND users.id <> :user_id
                    AND users.active = 1
                    AND IF(:name = '', 1, IF(users.name LIKE CONCAT('%', :name, '%'), 1, 0)) = 1
                LIMIT ".$nextPageLimit." OFFSET ".$offset,
                array(
                    "country_id" => $countryId,
                    "region_id" => $regionId,
                    "city_id" => $cityId,
                    "name" => $name,
                    "user_id" => $this->userId
                )
            );
        }

        if (count($users) > $limit) {
            array_pop($users);
            $hasMore = true;
        }

        foreach($users as &$user) {
            Utils::translateLocation($this->database, $user);
            $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
        }

        $this->response["data"] = $users;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function getSubscriptionSessionHandler() {
        // TODO - do this over $this->callSubscriptionsApi()
        // this would allow to avoid mysql completely

        // create session for subscriptions api
        $session = $this->database->fetchColumn("SELECT HEX(session) FROM subscription_sessions WHERE user_id = :user_id",
            array("user_id" => $this->userId));

        if (!$session) {
            $session = Utils::getRandom(15);

            $this->database->exec("INSERT INTO subscription_sessions (user_id, session, created)
            VALUES(:user_id, UNHEX(:session), :created)",
                array(
                    "user_id" => $this->userId,
                    "session" => $session,
                    "created" => time()
                ));
        }

        $this->response["subscription_session"] = strtolower($session);
        $this->output();
    }
}