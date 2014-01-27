<?php

abstract class Method {
    const OBJECT_TYPE_COMMENT = 1;
    const OBJECT_TYPE_FEED = 2;
    const OBJECT_TYPE_GALLERY = 3;
    const OBJECT_TYPE_GALLERY_IMAGE = 4;
    const OBJECT_TYPE_GROUP = 5;
    const OBJECT_TYPE_ARTICLE = 6;
    const OBJECT_TYPE_EVENT = 7;
    const OBJECT_TYPE_USER = 8;
    const OBJECT_TYPE_VOTE = 9;
    const OBJECT_TYPE_CONVERSATION = 10;

    protected $objectTypes = array(
        self::OBJECT_TYPE_COMMENT => "comment",
        self::OBJECT_TYPE_FEED => "post",
        self::OBJECT_TYPE_GALLERY => "gallery",
        self::OBJECT_TYPE_GALLERY_IMAGE => "gallery_image",
        self::OBJECT_TYPE_GROUP => "group",
        self::OBJECT_TYPE_ARTICLE => "article",
        self::OBJECT_TYPE_EVENT => "event",
        self::OBJECT_TYPE_USER => "user",
        self::OBJECT_TYPE_VOTE => "vote",
        self::OBJECT_TYPE_CONVERSATION => "conversation"
    );

    const IMAGE_ID_FEED = "f";
    const IMAGE_ID_GALLERY_IMAGE = "i";
    const IMAGE_ID_GROUP = "g";
    const IMAGE_ID_USER = "u";

    const VISIBILITY_PRIVATE = 0;
    const VISIBILITY_PUBLIC = 1;

    protected static $visibilityTypes = array(
        self::VISIBILITY_PRIVATE => "private",
        self::VISIBILITY_PUBLIC => "public"
    );

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var array
     */
    public $params;

    /**
     * @var array
     */
    public $response;

    /**
     * @var Database
     */
    public $database;

    public function __construct(Api $api) {
        $this->api = &$api;
        $this->params = &$api->requestParams;
        $this->database = &$api->database;
        $this->response = &$api->response;
    }

    public function output() {
        $this->api->output();
    }

    protected function requireParam($params) {
        if (!is_array($params)) {
            $params = array($params);
        }

        foreach ($params as $param)
            if (!isset($this->params[$param]))
                throw new Exception("Missing required parameter: {$param}");
    }

    protected function getObjectTypeId($typeName) {
        $typeId = array_search($typeName, $this->objectTypes);
        if (!$typeId)
            throw new Exception("Invalid type ID");

        return $typeId;
    }

    protected function callSubscriptionsApi($method, $params = array()) {
        // only proceed if enabled
        if (!(isset($this->api->config["subscriptions"]["enabled"]) &&
            $this->api->config["subscriptions"]["enabled"] === true))
            return null;

        $receiver = $this->api->config["subscriptions"]["port"];
        $secret =  $this->api->config["subscriptions"]["secret"];

        // this is not so important for us so ignore if it fails
        $sandbox = function($receiver, $secret, $method, $params) {
            // connect to service
            $fp = fsockopen($receiver, null, $errno, $errstr, 0.5);
            if (!$fp) return null;

            $params["method"] = $method;
            $data = json_encode($params);

            $request = "POST / HTTP/1.0\r\n" .
                "Host: localhost\r\n" .
                "Content-type: application/x-www-form-urlencoded\r\n" .
                "Content-Length: " . strlen($data) . "\r\n" .
                "Connection: Close\r\n" .
                "x-subscriptions-secret: " . $secret . "\r\n" .
                "\r\n" .
                $data .
                "\r\n";

            fwrite($fp, $request);

            $response = "";
            while (!feof($fp))
                $response .= fgets($fp, 1024);

            fclose($fp);

            list($header, $body) = explode("\r\n\r\n", $response, 2);
            return json_decode($body, true);
        };

        // run sandbox with silent exception handling
        $oldHandler = set_error_handler(function() {});
        $response = $sandbox($receiver, $secret, $method, $params);
        set_error_handler($oldHandler);

        return $response;
    }

    public abstract function run();
}

abstract class MethodHandler extends Method {
    public function run() {
        $method = $this->getCurrentMethod();

        if (!is_callable(array($this, $method . "Handler")))
            throw new Exception("No such method $method");

        call_user_func(array($this, $method . "Handler"));
    }

    protected function getCurrentMethod() {
        $path = $this->api->requestPath;
        $components = Utils::pathToComponents($path);

        $method = "default";
        if (isset($components[1]))
            $method = $components[1];

        return $method;
    }
}

abstract class LoggedUserMethodHandler extends MethodHandler {
    public $userId = null;
    public $user = array();
    public $publicMethods = array();

    protected function logActivity($activityTypeId, $groupId, $typeId = 0, $objectId = 0) {
        Activity::create(
            $this->database, $this->userId,
            $activityTypeId, $groupId, $typeId, $objectId
        );
    }

    protected function feedSaveStatus($message) {
        return Feed::saveStatus($this->database, $message);
    }

    protected function feedCreatePost($groupId, $feedTypeId, $feedStatusId, $feedImageId = 0, $feedLinkId = 0, $feedExtraId = 0,
                                        $postAsAdmin = false, $visibility = self::VISIBILITY_PRIVATE) {
        return Feed::createPost(
            $this->database, $this->userId,
            $groupId, $feedTypeId, $feedStatusId, $feedImageId, $feedLinkId, $feedExtraId,
            $postAsAdmin, $visibility
        );
    }

    protected function feedCreateAggregatedPost($groupId, $feedTypeId, $objectId, $data) {
        return Feed::createAggregatedPost(
            $this->database, $this->userId,
            $groupId, $feedTypeId, $objectId, $data
        );
    }

    protected function notificationCreate($userId, $fromUserId, $groupId, $notificationTypeId, $typeId = 0, $objectId = 0) {
        return Notification::create(
            $this->database, $userId, $fromUserId, $groupId,
            $notificationTypeId, $typeId, $objectId
        );
    }

    protected function hasPrivacy($groupId, $featureCode) {
        $privacy = (int)$this->database->fetchColumn("SELECT privacy FROM groups WHERE id = :group_id",
            array("group_id" => $groupId));

        if (Utils::hasFeature($privacy, $featureCode))
            return true;

        return false;
    }

    protected function groupIsAdmin($groupId) {
        // check creator
        $found = $this->database->fetchColumn("SELECT COUNT(1)
            FROM groups
            WHERE id = :group_id AND user_id = :user_id",
            array("group_id" => $groupId, "user_id" => $this->userId));

        if ($found > 0)
            return true;

        // look for admin
        $found = $this->database->fetchColumn("SELECT COUNT(1)
            FROM group_admins
            WHERE group_id = :group_id AND user_id = :user_id",
            array("group_id" => $groupId, "user_id" => $this->userId));

        if ($found > 0)
            return true;

        return false;
    }

    protected function groupIsMember($groupId) {
        $membership = $this->database->fetchColumn("SELECT COUNT(1)
            FROM group_members
            WHERE user_id = :user_id AND group_id = :group_id",
            array("user_id" => $this->userId, "group_id" => $groupId));

        return $membership > 0;
    }

    protected function groupRequireAdmin($groupId) {
        if (!$this->groupIsAdmin($groupId))
            throw new Exception("You must be admin of the group");
    }

    protected function groupRequireMember($groupId) {
        if (!$this->groupIsMember($groupId))
            throw new Exception("You must be member of the group");
    }

    protected function objectGetGroupId($objectTypeId, $objectId) {
        switch ($objectTypeId) {
            case self::OBJECT_TYPE_ARTICLE:
                $groupId = $this->database->fetchColumn("SELECT group_id FROM articles WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_EVENT:
                $groupId = $this->database->fetchColumn("SELECT group_id FROM events WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_COMMENT:
                $params = $this->database->fetch("SELECT type_id,object_id FROM comments WHERE id = :object_id",
                    array("object_id" => $objectId));

                if (!$params)
                    throw new Exception("Error resolving comment");

                $groupId = $this->objectGetGroupId($params["type_id"], $params["object_id"]);
                break;

            case self::OBJECT_TYPE_FEED:
                $groupId = $this->database->fetchColumn("SELECT group_id FROM feed WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_GALLERY:
                $groupId = $this->database->fetchColumn("SELECT group_id FROM gallery WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_GALLERY_IMAGE:
                $galleryId = $this->database->fetchColumn("SELECT gallery_id FROM gallery_images WHERE id = :object_id",
                    array("object_id" => $objectId));

                return $this->objectGetGroupId(self::OBJECT_TYPE_GALLERY, $galleryId);
                break;

            default:
                throw new Exception("Unable to get group for given object");
        }

        return $groupId;
    }

    protected function objectGetUserId($objectTypeId, $objectId) {
        switch ($objectTypeId) {
            case self::OBJECT_TYPE_COMMENT:
                $userId = $this->database->fetchColumn("SELECT user_id FROM comments WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_VOTE:
                $userId = $this->database->fetchColumn("SELECT user_id FROM voting WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_ARTICLE:
                $userId = $this->database->fetchColumn("SELECT user_id FROM articles WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_EVENT:
                $userId = $this->database->fetchColumn("SELECT user_id FROM events WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_FEED:
                $userId = $this->database->fetchColumn("SELECT user_id FROM feed WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_GALLERY:
                $userId = $this->database->fetchColumn("SELECT user_id FROM gallery WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_GALLERY_IMAGE:
                $galleryId = $this->database->fetchColumn("SELECT gallery_id FROM gallery_images WHERE id = :object_id",
                    array("object_id" => $objectId));

                return $this->objectGetUserId(self::OBJECT_TYPE_GALLERY, $galleryId);
                break;

            default:
                return 0;
        }

        return $userId;
    }

    protected function objectIsPublic($objectTypeId, $objectId) {
        switch ($objectTypeId) {
            case self::OBJECT_TYPE_ARTICLE:
                $visibility = $this->database->fetchColumn("SELECT visibility FROM articles WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_EVENT:
                $visibility = $this->database->fetchColumn("SELECT visibility FROM events WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_FEED:
                $visibility = $this->database->fetchColumn("SELECT visibility FROM feed WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_GALLERY:
                $visibility = $this->database->fetchColumn("SELECT visibility FROM gallery WHERE id = :object_id",
                    array("object_id" => $objectId));
                break;

            case self::OBJECT_TYPE_COMMENT:
                $comment = $this->database->fetch("SELECT comments.type_id, comments.object_id FROM comments WHERE id = :object_id",
                    array("object_id" => $objectId));

                return $this->objectIsPublic($comment["type_id"], $comment["object_id"]);
                break;

            case self::OBJECT_TYPE_GALLERY_IMAGE:
                $galleryId = $this->database->fetchColumn("SELECT gallery_id FROM gallery_images WHERE id = :object_id",
                    array("object_id" => $objectId));

                return $this->objectIsPublic(self::OBJECT_TYPE_GALLERY, $galleryId);
                break;

            default:
                return false;
        }

        return $visibility == self::VISIBILITY_PUBLIC;
    }

    protected function objectIsMember($objectTypeId, $objectId) {
        $groupId = $this->objectGetGroupId($objectTypeId, $objectId);
        if (!$groupId)
            return true;
        return $this->groupIsMember($groupId);
    }

    protected function objectIsAdmin($objectTypeId, $objectId) {
        $groupId = $this->objectGetGroupId($objectTypeId, $objectId);
        return $this->groupIsAdmin($groupId);
    }

    protected function objectIsOwner($objectTypeId, $objectId) {
        $userId = $this->objectGetUserId($objectTypeId, $objectId);
        return (int)$userId === (int)$this->userId;
    }

    protected function objectRequireMember($objectTypeId, $objectId) {
        if (!$this->objectIsMember($objectTypeId, $objectId))
            throw new Exception("You must be member of the parent group");
    }

    protected function objectRequireAdmin($objectTypeId, $objectId) {
        if (!$this->objectIsAdmin($objectTypeId, $objectId))
            throw new Exception("You must be admin of the parent group");
    }

    protected function objectRequireOwner($objectTypeId, $objectId) {
        if (!$this->objectIsOwner($objectTypeId, $objectId))
            throw new Exception("You must be owner of the object");
    }

    public function run() {
        // exclude method if is public
        if (in_array($this->getCurrentMethod(), $this->publicMethods) && !isset($this->params["session"])) {
            MethodHandler::run();
            return;
        }

        // require valid session
        $this->requireParam(array("session"));

        $userId = $this->database->fetchColumn("SELECT user_id FROM sessions WHERE session = UNHEX(:session)",
            array("session" => $this->params["session"]));

        if (!$userId) {
            throw new Exception("Invalid session");
        }

        $userDetail = $this->database->fetch("SELECT
                users.id, users.first_name,users.last_name,users.name,
                users.birthday,users.email,users.created,
                users.active,users.verified,
                users.image,users.gender,
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
            WHERE users.id = :user_id",
            array("user_id" => $userId));

        if ($userDetail["verified"] != "1")
            throw new Exception("User not verified");

        if ($userDetail["active"] != "1")
            throw new Exception("User account not active");

        Utils::translateLocation($this->database, $userDetail);

        $userDetail["gender"] = User::getGenderName($userDetail["gender"]);
        $userDetail["birthday"] = Utils::fromBirthday($userDetail["birthday"]);
        $userDetail["age"] = Utils::getAge($userDetail["birthday"]);
        $userDetail["image"] = Utils::updateImageUrl($userDetail["image"], $this->api->config["images_url"]);
        $userDetail["created"] = Utils::updateTime($userDetail["created"], $this->api->config["time_format"]);

        $this->userId = $userId;
        $this->user = $userDetail;

        MethodHandler::run();
    }
}