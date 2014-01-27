<?php

class Moderation extends MethodHandler
{
    const LATEST_GROUPS = 1;
    const LATEST_USERS = 2;

    public static $latestTypes = array(
        self::LATEST_GROUPS => "groups",
        self::LATEST_USERS => "users",
    );

    const REPORT_GROUPS = 1;
    const REPORT_USERS = 2;

    public static $reportTypes = array(
        self::REPORT_GROUPS => "groups",
        self::REPORT_USERS => "users",
    );

    private $moderationUser = null;

    public function run() {
        // somebody might be listening
        // require https://
        if ($this->api->config["moderation_require_https"] && !Utils::isConnectionSecure())
            throw new Exception("This method requires HTTPS connection");

        // allow login method without moderation session passed
        if ($this->getCurrentMethod() == "login" && !isset($this->params["moderation_session"])) {
            MethodHandler::run();
            return;
        }

        // require moderation session
        $this->requireParam("moderation_session");

        $this->moderationUser = $this->database->fetch("SELECT id,email FROM moderation_users WHERE session = UNHEX(:session)",
            array("session" => $this->params["moderation_session"]));

        if (!$this->moderationUser)
            throw new Exception("Invalid session");

        MethodHandler::run();
    }

    public function loginHandler() {
        $this->requireParam(array("email", "password"));

        $password = $this->params["password"];
        $email = $this->params["email"];
        $moderationUser = $this->database->fetch("SELECT id,HEX(password) as password,ip FROM moderation_users WHERE email = :email", array("email" => $email));

        if (!$moderationUser)
            throw new Exception("No such user");

        $moderationUserId = $moderationUser["id"];
        $storedPassword = $moderationUser["password"];

        if (strncasecmp($storedPassword, hash("sha512", $password), strlen($storedPassword)) !== 0)
            throw new Exception("Invalid password");

        // information about previous session
        $ip = $moderationUser["ip"];
        $ipData = Utils::geoipGetRecord($moderationUser["ip"]);
        $countryCode = isset($ipData["country_code"]) ? $ipData["country_code"] : "";
        $cityName = isset($ipData["city"]) ? $ipData["city"] : "";
        $location = array("country" => $countryCode, "city" => $cityName);

        // create a new session
        $this->response["session"] = $this->createSession($moderationUserId);
        $this->response["previous_session_ip"] = $ip;
        $this->response["previous_session_location"] = $location;

        $this->response["success"] = 1;
        $this->output();
    }

    public function meHandler() {
        $this->response = $this->moderationUser;
        $this->output();
    }

    public function listLatestHandler() {
        $this->requireParam(array("latest_type"));

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

        $search = "";
        if (isset($this->params["search"]))
            $search = $this->params["search"];

        $typeId = array_search($this->params["latest_type"], self::$latestTypes);
        $nextPageLimit = $limit + 1;
        $hasMore = false;

        if ($typeId == self::LATEST_GROUPS) {
            $latestEntries = $this->database->fetchAll("SELECT
                    groups.id,
                    groups.title,
                    groups.image,
                    groups.created,
                    groups.active,
                    groups.user_id,
                    IFNULL(group_summary.members_count, 0) as members,
                    IFNULL(group_summary.posts_count, 0) as posts_count,
                    IFNULL(group_summary.posts_visible, 0) as posts_visible_count,
                    group_locations.location_city,
                    group_locations.location_country,
                    group_locations.location_region
                FROM groups
                    LEFT JOIN group_summary ON group_summary.group_id = groups.id
                    LEFT JOIN group_locations ON group_locations.group_id = groups.id
                WHERE 1 = 1
                    AND IF(:search = '', 1, IF(groups.title LIKE CONCAT('%', :search, '%'), 1, 0)) = 1 " .
                ($sinceId > 0 ? " AND groups.id > {$sinceId} " : "") .
                ($untilId > 0 ? " AND groups.id < {$untilId} " : "") .
                "ORDER BY groups.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}", array("search" => $search));
        }
        else if ($typeId == self::LATEST_USERS) {
            $latestEntries = $this->database->fetchAll("SELECT
                    users.id,
                    users.name,
                    users.image,
                    users.created,
                    users.active,
                    user_locations.location_city,
                    user_locations.location_country,
                    user_locations.location_region
                FROM users
                    LEFT JOIN user_locations ON user_locations.user_id = users.id
                WHERE 1 = 1
                    AND IF(:search = '', 1, IF(users.name LIKE CONCAT('%', :search, '%'), 1, 0)) = 1 " .
                ($sinceId > 0 ? " AND users.id > {$sinceId} " : "") .
                ($untilId > 0 ? " AND users.id < {$untilId} " : "") .
                "ORDER BY users.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}", array("search" => $search));
        }
        else {
            throw new Exception("Invalid type ID");
        }

        if (count($latestEntries) > $limit) {
            array_pop($latestEntries);
            $hasMore = true;
        }

        foreach($latestEntries as &$latest) {
            $latest["image"] = Utils::updateImageUrl($latest["image"], $this->api->config["images_url"]);
            $latest["created"] = Utils::updateTime($latest["created"], $this->api->config["time_format"]);
            Utils::translateLocation($this->database, $latest);
        }

        $this->response["data"] = $latestEntries;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listReportsHandler() {
        $this->requireParam("report_type");

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

        $typeId = array_search($this->params["report_type"], self::$reportTypes);
        $nextPageLimit = $limit + 1;
        $hasMore = false;

        if ($typeId == self::REPORT_GROUPS) {
            $reportEntries = $this->database->fetchAll("SELECT
                    group_reports.id,
                    group_reports.message,
                    group_reports.user_id as reporter_user_id,
                    group_reports.created,
                    groups.id as group_id,
                    groups.title,
                    groups.image,
                    groups.active,
                    groups.user_id,
                    IFNULL(group_summary.members_count, 0) as members,
                    IFNULL(group_summary.posts_count, 0) as posts_count,
                    IFNULL(group_summary.posts_visible, 0) as posts_visible_count
                FROM group_reports
                    LEFT JOIN groups ON groups.id = group_reports.group_id
                    LEFT JOIN group_summary ON group_summary.group_id = groups.id
                WHERE 1 = 1 " .
                ($sinceId > 0 ? " AND group_reports.id > {$sinceId} " : "") .
                ($untilId > 0 ? " AND group_reports.id < {$untilId} " : "") .
                "ORDER BY group_reports.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}");
        }
        else if ($typeId == self::REPORT_USERS) {
            $reportEntries = $this->database->fetchAll("SELECT
                    user_reports.id,
                    user_reports.message,
                    user_reports.user_id as reporter_user_id,
                    user_reports.created,
                    users.id as user_id,
                    users.name,
                    users.image,
                    users.active
                FROM user_reports
                    LEFT JOIN users ON users.id = user_reports.reported_user_id
                WHERE 1 = 1 " .
                ($sinceId > 0 ? " AND user_reports.id > {$sinceId} " : "") .
                ($untilId > 0 ? " AND user_reports.id < {$untilId} " : "") .
                "ORDER BY users.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}");
        }
        else {
            throw new Exception("Invalid type ID");
        }

        if (count($reportEntries) > $limit) {
            array_pop($reportEntries);
            $hasMore = true;
        }

        foreach($reportEntries as &$report) {
            $report["image"] = Utils::updateImageUrl($report["image"], $this->api->config["images_url"]);
            $report["created"] = Utils::updateTime($report["created"], $this->api->config["time_format"]);
        }

        $this->response["data"] = $reportEntries;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function deleteReportHandler() {
        $this->requireParam("report_id");
        $this->requireParam("report_type");

        $reportId = $this->params["report_id"];

        $typeId = array_search($this->params["report_type"], self::$reportTypes);
        if ($typeId == self::REPORT_GROUPS) {
            $this->database->exec("DELETE FROM group_reports WHERE id = :report_id",
                array("report_id" => $reportId));
        }
        else if ($typeId == self::REPORT_USERS) {
            $this->database->exec("DELETE FROM user_reports WHERE id = :report_id",
                array("report_id" => $reportId));
        }
        else {
            throw new Exception("Invalid type ID");
        }

        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteGroupHandler() {
        $this->requireParam("group_id");
        $groupId = $this->params["group_id"];

        $this->database->exec("UPDATE groups SET active = :active WHERE id = :group_id",
            array("active" => 0, "group_id" => $groupId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteUserHandler() {
        $this->requireParam("user_id");
        $userId = $this->params["user_id"];

        $this->database->exec("UPDATE users SET active = :active WHERE id = :user_id",
            array("active" => 0, "user_id" => $userId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function groupDetailHandler() {
        $this->requireParam("group_id");

        $group = $this->database->fetch("SELECT
                groups.id, groups.active, groups.created, groups.title, groups.link,
                groups.description, groups.image,
                groups.user_id as user_id,
                groups.privacy,
                IFNULL(group_summary.members_count, 0) as members,
                IFNULL(group_summary.posts_count, 0) as posts_count,
                IFNULL(group_summary.posts_visible, 0) as posts_visible_count,
                group_locations.location_city,
                group_locations.location_country,
                group_locations.location_region
            FROM groups
                LEFT JOIN group_summary ON group_summary.group_id = groups.id
                LEFT JOIN group_locations ON group_locations.group_id = groups.id
            WHERE groups.id = :group_id",
            array("group_id" => $this->params["group_id"]));

        if (!$group)
            throw new Exception("No such group");

        $group["image"] = Utils::updateImageUrl($group["image"], $this->api->config["images_url"]);
        $group["created"] = Utils::updateTime($group["created"], $this->api->config["time_format"]);
        Utils::translateLocation($this->database, $group);

        $this->response = $group;
        $this->output();
    }

    public function userDetailHandler() {
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
                user_locations.location_city,
                user_locations.location_country,
                user_locations.location_region
            FROM users
                LEFT JOIN user_locations ON user_locations.user_id = users.id
                LEFT JOIN user_summary ON user_summary.user_id = users.id
            WHERE users.id = :user_id",
            array("user_id" => $this->params["user_id"]));

        if (!$user)
            throw new Exception("No such user");

        Utils::translateLocation($this->database, $user);
        $user["gender"] = User::getGenderName($user["gender"]);
        $user["birthday"] = Utils::fromBirthday($user["birthday"]);
        $user["age"] = Utils::getAge($user["birthday"]);
        $user["image"] = Utils::updateImageUrl($user["image"], $this->api->config["images_url"]);
        $user["created"] = Utils::updateTime($user["created"], $this->api->config["time_format"]);

        $this->response = $user;
        $this->output();
    }

    private function createSession($userId) {
        $session = Utils::getRandom(20);

        // get and update ip address, as well as location
        $ip = $_SERVER["REMOTE_ADDR"];

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];

        $this->database->exec("UPDATE moderation_users SET ip = :ip, session = UNHEX(:session) WHERE id = :moderation_user_id",
            array("moderation_user_id" => $userId, "ip" => $ip, "session" => $session));

        return $session;
    }
}