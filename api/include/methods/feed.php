<?php

class Feed extends LoggedUserMethodHandler
{
    const FEED_STATUS_UNREAD = 0;
    const FEED_STATUS_READ = 1;

    private static $feedStatusTypes = array(
        self::FEED_STATUS_UNREAD => "unread",
        self::FEED_STATUS_READ => "read",
    );

    const FEED_TYPE_MESSAGE = 1;
    const FEED_TYPE_IMAGE = 2;
    const FEED_TYPE_LINK = 3;

    const FEED_TYPE_ARTICLE = 4;
    const FEED_TYPE_GALLERY = 5;
    const FEED_TYPE_EVENT = 6;

    private static $feedTypes = array(
        self::FEED_TYPE_MESSAGE => "message",
        self::FEED_TYPE_IMAGE => "image",
        self::FEED_TYPE_LINK => "link",

        self::FEED_TYPE_ARTICLE => "article",
        self::FEED_TYPE_GALLERY => "gallery",
        self::FEED_TYPE_EVENT => "event",
    );

    const FEED_AGGREGATION_TIME = 3600;
    public $publicMethods = array("detail", "list");

    public function listHandler() {
        $this->requireParam("group_id");

        $groupId = $this->params["group_id"];

        $isMember = $this->groupIsMember($groupId);
        $isAdmin = $this->groupIsAdmin($groupId);

        $limit = 10;
        $offset = 0;
        $sinceId = 0;
        $untilId = 0;
        $typeId = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        if (isset($this->params["since_id"]))
            $sinceId = (int)$this->params["since_id"];

        if (isset($this->params["until_id"]))
            $untilId = (int)$this->params["until_id"];

        if (isset($this->params["type"])) {
            $type = $this->params["type"];
            $typeId = (int)array_search($type, self::$feedTypes);
        }

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $posts = $this->database->fetchAll("SELECT
                feed.id,
                feed.created,
                feed.group_id,
                feed.visibility,
                feed.feed_type,

                IFNULL(users.id, 0) as user_id,
                users.name as user_name,
                users.image as user_image,

                feed_images.image as feed_images_image,

                feed_links.title as feed_links_title,
                feed_links.description as feed_links_description,
                feed_links.image as feed_links_image,
                feed_links.host as feed_links_host,
                feed_links.url as feed_links_url,

                feed_statuses.message as feed_statuses_message,
                feed_extra.data as extra_data,

                IFNULL(feed_read.status,0) as read_status,
                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM feed
                LEFT JOIN users ON users.id = feed.user_id
                LEFT JOIN feed_images ON feed_images.id = feed.feed_image_id
                LEFT JOIN feed_links ON feed_links.id = feed.feed_link_id
                LEFT JOIN feed_statuses ON feed_statuses.id = feed.feed_status_id
                LEFT JOIN feed_extra ON feed_extra.id = feed.feed_extra_id

                LEFT JOIN feed_read ON (feed_read.user_id = :user_id AND feed_read.post_id = feed.id)
                LEFT JOIN comment_summary ON (comment_summary.object_id = feed.id AND comment_summary.type_id = :object_type_feed)
                LEFT JOIN voting_summary ON (voting_summary.object_id = feed.id AND voting_summary.type_id = :object_type_feed)

            WHERE feed.group_id = :group_id AND feed.active = 1 AND
                IF(:type = 0, 1, IF(feed.feed_type = :type, 1, 0)) = 1 AND
                IF(:is_member = 1, 1, IF(feed.visibility = :visibility_public, 1, 0)) = 1 " .
            ($sinceId > 0 ? " AND feed.id > {$sinceId} " : "").
            ($untilId > 0 ? " AND feed.id < {$untilId} " : "").
            "ORDER BY feed.id DESC LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "group_id" => $groupId,
                "type" => $typeId,
                "object_type_feed" => self::OBJECT_TYPE_FEED,
                "user_id" => $this->userId,
                "visibility_public" => self::VISIBILITY_PUBLIC,
                "is_member" => (int)$isMember
            )
        );

        if (count($posts) > $limit) {
            array_pop($posts);
            $hasMore = true;
        }

        foreach ($posts as &$post) {
            $this->formatDetail($post);
            if ($isAdmin)
                $post["can_edit"] = true;
        }

        // update read range
        if (count($posts) > 0) {
            // get range
            $fromPostId = $posts[count($posts) - 1]["id"]; // oldest post
            $toPostId = $posts[0]["id"]; // latest post

            if (isset($this->params["mark_as_read"]) && $this->params["mark_as_read"] == "1")
                $this->markAsRead($groupId, $fromPostId, $toPostId);
        }

        $this->response["data"] = $posts;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function markAsReadHandler() {
        $this->requireParam("group_id");
        $this->requireParam("from_post_id");
        $this->requireParam("to_post_id");
        $this->groupRequireMember($this->params["group_id"]);

        $groupId = $this->params["group_id"];
        $fromPostId = $this->params["from_post_id"];
        $toPostId = $this->params["to_post_id"];

        $this->markAsRead($groupId, $fromPostId, $toPostId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("post_id");
        $postId = $this->params["post_id"];

        $post = $this->database->fetch("SELECT
                feed.id,
                feed.created,
                feed.group_id,
                feed.visibility,
                feed.feed_type,

                IFNULL(users.id, 0) as user_id,
                users.name as user_name,
                users.image as user_image,

                feed_images.image as feed_images_image,

                feed_links.title as feed_links_title,
                feed_links.description as feed_links_description,
                feed_links.image as feed_links_image,
                feed_links.host as feed_links_host,
                feed_links.url as feed_links_url,

                feed_statuses.message as feed_statuses_message,
                feed_extra.data as extra_data,

                IFNULL(feed_read.status,0) as read_status,
                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM feed
                LEFT JOIN users ON users.id = feed.user_id
                LEFT JOIN feed_images ON feed_images.id = feed.feed_image_id
                LEFT JOIN feed_links ON feed_links.id = feed.feed_link_id
                LEFT JOIN feed_statuses ON feed_statuses.id = feed.feed_status_id
                LEFT JOIN feed_extra ON feed_extra.id = feed.feed_extra_id

                LEFT JOIN feed_read ON (feed_read.user_id = :user_id AND feed_read.post_id = feed.id)
                LEFT JOIN comment_summary ON (comment_summary.object_id = feed.id AND comment_summary.type_id = :object_type_feed)
                LEFT JOIN voting_summary ON (voting_summary.object_id = feed.id AND voting_summary.type_id = :object_type_feed)
            WHERE feed.id = :post_id AND feed.active = 1",
            array(
                "post_id" => $postId,
                "object_type_feed" => self::OBJECT_TYPE_FEED,
                "user_id" => $this->userId,
            )
        );

        if (!$post)
            throw new Exception("No such post");

        if ($post["visibility"] != self::VISIBILITY_PUBLIC)
            $this->groupRequireMember($post["group_id"]);

        $this->formatDetail($post);

        if ($this->groupIsAdmin($post["group_id"]))
            $post["can_edit"] = true;

        $this->response = $post;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("post_id");

        $postId = $this->params["post_id"];
        $groupId = $this->postGetGroupId($postId);

        // require owner or admin of the group
        if (!$this->postIsOwner($postId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        // check if post is active
        $active =  $this->database->fetchColumn("SELECT COUNT(1) FROM feed WHERE id = :post_id AND active = 1",
            array("post_id" => $postId));

        if (!$active)
            throw new Exception("No such post");


        $this->database->beginTransaction();

        // set as inactive
        $this->database->exec("UPDATE feed SET active = :active WHERE id = :post_id",
            array("active" => 0, "post_id" => $postId));

        // DO NOT UPDATE posts_count in group_summary since it holds real information about count of posts
        // and DO NOT unmark read
        $this->database->exec("INSERT INTO group_summary(group_id, members_count, posts_count, posts_visible) VALUES (:group_id, 0, 0, 0)
            ON DUPLICATE KEY UPDATE posts_visible = GREATEST(0, posts_visible - 1)",
            array(
                "group_id" => $groupId
            )
        );

        $this->database->commit();

        $this->logActivity(Activity::ACTIVITY_GROUP_FEED_DELETE, $groupId, self::OBJECT_TYPE_FEED, $postId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function setVisibilityHandler() {
        $this->requireParam("post_id");
        $this->requireParam("visibility");

        $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);

        $postId = $this->params["post_id"];
        $groupId = $this->postGetGroupId($postId);

        // require owner or admin of the group
        if (!$this->postIsOwner($postId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        // set as inactive
        $this->database->exec("UPDATE feed SET visibility = :visibility WHERE id = :post_id",
            array("visibility" => $visibility, "post_id" => $postId));

        $this->response["success"] = 1;
        $this->output();
    }

    public function postHandler() {
        $this->requireParam("group_id");
        $this->requireParam("message");

        $groupId = $this->params["group_id"];
        $message = $this->params["message"];
        $feedType = self::FEED_TYPE_MESSAGE;
        $visibility = self::VISIBILITY_PRIVATE;

        // change visibility
        if (isset($this->params["visibility"]))
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);

        // check permissions
        if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_ADMIN_POSTS_ONLY))
            $this->groupRequireAdmin($groupId);
        else
            $this->groupRequireMember($groupId);

        // check message
        if (strlen(trim($message)) == 0)
            throw new Exception("Message is empty");

        $feedImageId = $this->saveImage();
        $feedStatusId = $this->feedSaveStatus($message);
        $feedLinkId = 0;

        if (isset($this->params["link_id"]))
            $feedLinkId = (int)$this->params["link_id"];

        if ($feedImageId != 0)
            $feedType = self::FEED_TYPE_IMAGE;

        if ($feedLinkId != 0)
            $feedType = self::FEED_TYPE_LINK;

        $postAsAdmin = false;
        if (isset($this->params["post_as_admin"]) && (int)$this->params["post_as_admin"]) {
            $this->groupRequireAdmin($groupId);
            $postAsAdmin = true;
        }

        $postId = $this->feedCreatePost($groupId, $feedType, $feedStatusId, $feedImageId, $feedLinkId, 0, $postAsAdmin, $visibility);

        $this->logActivity(Activity::ACTIVITY_GROUP_FEED_POST, $groupId, self::OBJECT_TYPE_FEED, $postId);
        $this->response["post_id"] = $postId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function linkDetailHandler() {
        $this->requireParam("url");
        $url = $this->params["url"];

        // TODO do not necessary redownload if exists
        // TODO this potentially sucks if someone loads big file

        $headers = array(
            "Accept-Language: en-us,en;q=0.5"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RANGE, "0-2048");

        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // TODO do we need to take care of encoding?!
        if (isset($info["content_type"])) {
        }

        // update url after redirects
        $url = isset($info["url"]) ? $info["url"] : $url;

        // parse out structure
        libxml_use_internal_errors(true);

        $doc = new DomDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $metaElements = $xpath->query("//*/meta[starts-with(@property, 'og:')]");
        $titleElements = $xpath->query("//title");
        $descriptionElements = $xpath->query("//*/meta[@name='description']");

        $og = array();

        foreach ($metaElements as $meta)
            $og[$meta->getAttribute('property')] = $meta->getAttribute('content');

        if ($titleElements->length > 0 && (!isset($og["og:title"]) || empty($og["og:title"])))
            $og["og:title"] = $titleElements->item(0)->nodeValue;

        if ($descriptionElements->length > 0 && (!isset($og["og:description"]) || empty($og["og:description"])))
            $og["og:description"] = $descriptionElements->item(0)->getAttribute('content');

        $host = parse_url($url, PHP_URL_HOST);
        $title = isset($og["og:title"]) ? $og["og:title"] : "";
        $description = isset($og["og:description"]) ? $og["og:description"] : "";
        $image = isset($og["og:image"]) ? $og["og:image"] : "";

        if ($image != "") {
            // check if there is proper prefix before url, if no add base path
            if (!preg_match("/^(https?:)?\\/\\//", $image))
                $image = "http://" . $host . "/" . ltrim($image, "/");
        }

        $this->database->exec("INSERT INTO feed_links(title, description, host, url, image, created)
            VALUES(:title, :description, :host, :url, :image, :created) ON DUPLICATE KEY UPDATE
            title = :title, description = :description, host = :host, image = :image",
            array(
                "title" => $title,
                "description" => $description,
                "host" => $host,
                "url" => $url,
                "image" => $image,
                "created" => time(),
            )
        );

        // always fetch, can not use last insert id because of duplicate key update
        $linkId = $this->database->fetchColumn("SELECT id FROM feed_links WHERE url = :url",
            array("url" => $url));

        $this->response = array(
            "id" => $linkId,
            "title" => $title,
            "description" => $description,
            "host" => $host,
            "url" => $url,
            "image" => $image,
        );

        $this->output();
    }

    private function saveImage() {
        if (!isset($_FILES["image"]))
            return 0;

        $uploadPath = $this->api->config["images_upload_path"];
        $fileName = Utils::saveImage($_FILES["image"]["tmp_name"], $uploadPath, self::IMAGE_ID_FEED . $this->userId);

        // save new image
        $this->database->exec("INSERT INTO feed_images(image) VALUES(:image)",
            array(
                "image" => $fileName
            )
        );

        return $this->database->lastInsertId();
    }

    private function markAsRead($groupId, $fromPostId, $toPostId) {
        // no need to mark anything if user is not logged in
        if (!$this->user)
            return;

        if ($fromPostId > $toPostId)
            throw new Exception("Range is invalid");

        // check if there are active posts before from read id
        // if there are not, there could be some inactive posts
        // we need to mark them as read
        $this->extendPostsRange($groupId, $fromPostId, $toPostId);

        // get feed posts (also inactive) that are not read
        $unreadPostIds = $this->database->fetchAllColumn("SELECT id
            FROM feed
                LEFT JOIN feed_read ON (feed_read.user_id = :user_id AND feed_read.post_id = feed.id)
            WHERE feed.group_id = :group_id
                AND (IFNULL(feed_read.status, 0) <> :feed_status_read)
                AND feed.id BETWEEN :from_post_id AND :to_post_id",
            array(
                "group_id" => $groupId,
                "from_post_id" => $fromPostId,
                "to_post_id" => $toPostId,
                "user_id" => $this->userId,
                "feed_status_read" => self::FEED_STATUS_READ
            )
        );

        if (count($unreadPostIds) > 0) {
            $this->database->beginTransaction();

            // mark as read
            foreach ($unreadPostIds as $postId) {
                $this->database->exec("INSERT INTO feed_read(user_id, post_id, status)
                    VALUES(:user_id, :post_id, :status)
                    ON DUPLICATE KEY UPDATE status = :status",
                    array("user_id" => $this->userId, "post_id" => $postId, "status" => self::FEED_STATUS_READ));
            }

            // update read and summary
            $this->database->exec("INSERT INTO feed_read_summary (user_id, group_id, read_count)
                    VALUES(:user_id, :group_id, :read_count) ON DUPLICATE KEY UPDATE
                        read_count = read_count + :read_count",
                array(
                    "group_id" => $groupId,
                    "user_id" => $this->userId,
                    "read_count" => count($unreadPostIds)
                )
            );

            $this->database->commit();
        }
    }


    private function extendPostsRange($groupId, &$fromPostId, &$toPostId) {
        $activePostsBeforeFrom = $this->database->fetchColumn("SELECT id FROM feed
            WHERE group_id = :group_id AND active = 1 AND id < :from_post_id ORDER BY id ASC LIMIT 1",
            array("group_id" => $groupId, "from_post_id" => $fromPostId)
        );

        $inactivePostsBeforeFrom = $this->database->fetchColumn("SELECT id FROM feed
            WHERE group_id = :group_id AND active = 0 AND id < :from_post_id ORDER BY id ASC LIMIT 1",
            array("group_id" => $groupId, "from_post_id" => $fromPostId)
        );

        // there are no active posts before first post in range so extend it
        if (!$activePostsBeforeFrom && $inactivePostsBeforeFrom) {
            $fromPostId = $inactivePostsBeforeFrom;
        }

        $activePostsAfterTo = $this->database->fetchColumn("SELECT id FROM feed
            WHERE group_id = :group_id AND active = 1 AND id > :to_post_id ORDER BY id DESC LIMIT 1",
            array("group_id" => $groupId, "to_post_id" => $toPostId)
        );

        $inactivePostsAfterTo = $this->database->fetchColumn("SELECT id FROM feed
            WHERE group_id = :group_id AND active = 0 AND id > :to_post_id ORDER BY id DESC LIMIT 1",
            array("group_id" => $groupId, "to_post_id" => $toPostId)
        );

        // there are no active posts after last post in range so extend it
        if (!$activePostsAfterTo && $inactivePostsAfterTo) {
            $toPostId = $inactivePostsAfterTo;
        }
    }

    private function formatDetail(&$post) {
        $post["user"] = array();

        if (!empty($post["user_id"])) {
            $post["user"]["id"] = $post["user_id"];
            $post["user"]["image"] = Utils::updateImageUrl($post["user_image"], $this->api->config["images_url"]);
            $post["user"]["name"] = $post["user_name"];
        }

        unset($post["user_id"]);
        unset($post["user_name"]);
        unset($post["user_image"]);

        $post["created"] = Utils::updateTime($post["created"], $this->api->config["time_format"]);
        $post["can_edit"] = false;

        // owner can edit
        if (!empty($post["user"])) {
            $post["can_edit"] = (int)$post["user"]["id"] === (int)$this->userId;
        }

        // can vote & comment
        $post["can_interact"] = $this->userId != null;

        // extract extra data
        if (array_key_exists("extra_data", $post)) {
            $post["extra"] = json_decode($post["extra_data"]);
            unset($post["extra_data"]);
        }

        if (array_key_exists("visibility", $post)) {
            $post["visibility"] = self::$visibilityTypes[$post["visibility"]];
        }

        if (array_key_exists("read_status", $post)) {
            $post["read_status"] = self::$feedStatusTypes[$post["read_status"]];
        }

        if (array_key_exists("feed_type", $post)) {
            $post["feed_type"] = self::$feedTypes[$post["feed_type"]];
        }

        if (array_key_exists("feed_statuses_message", $post)) {
            $post["status"] = null;
            if (!empty($post["feed_statuses_message"])) {
                $post["status"]["message"] = $post["feed_statuses_message"];
            }

            unset($post["feed_statuses_message"]);
        }

        if (array_key_exists("feed_links_title", $post) &&
            array_key_exists("feed_links_description", $post) &&
            array_key_exists("feed_links_image", $post) &&
            array_key_exists("feed_links_host", $post) &&
            array_key_exists("feed_links_url", $post)) {

            $post["link"] = null;
            if (!empty($post["feed_links_url"])) {
                $post["link"]["title"] = $post["feed_links_title"];
                $post["link"]["description"] = $post["feed_links_description"];
                $post["link"]["image"] = $post["feed_links_image"];
                $post["link"]["url"] = $post["feed_links_url"];
                $post["link"]["host"] = $post["feed_links_host"];
            }

            unset($post["feed_links_title"]);
            unset($post["feed_links_description"]);
            unset($post["feed_links_image"]);
            unset($post["feed_links_host"]);
            unset($post["feed_links_url"]);
        }

        if (array_key_exists("feed_images_image", $post)) {
            if (!empty($post["feed_images_image"])) {
                $post["status"]["image_full"] = Utils::updateImageUrl($post["feed_images_image"], $this->api->config["images_formats"]["org"]);
                $post["status"]["image"] = Utils::updateImageUrl($post["feed_images_image"], $this->api->config["images_url"]);
            }

            unset($post["feed_images_image"]);
        }
    }

    static public function createPost($database, $userId, $groupId, $feedTypeId, $feedStatusId, $feedImageId = 0, $feedLinkId = 0, $feedExtraId = 0,
                                         $postAsAdmin = false, $visibility = self::VISIBILITY_PRIVATE) {
        $database->beginTransaction();
        $database->exec("INSERT INTO feed(active, visibility, feed_type, group_id, user_id, created, feed_status_id, feed_image_id, feed_link_id, feed_extra_id)
            VALUES(:active, :visibility, :feed_type, :group_id, :user_id, :created, :feed_status_id, :feed_image_id, :feed_link_id, :feed_extra_id)",
            array(
                "active" => 1,
                "visibility" => $visibility,
                "group_id" => $groupId,
                "feed_type" => $feedTypeId,
                "user_id" => $postAsAdmin ? 0 : $userId,
                "created" => time(),
                "feed_status_id" => $feedStatusId,
                "feed_image_id" => $feedImageId,
                "feed_link_id" => $feedLinkId,
                "feed_extra_id" => $feedExtraId
            )
        );

        $postId = $database->lastInsertId();

        // update summary
        $database->exec("INSERT INTO group_summary(group_id, members_count, posts_count, posts_visible) VALUES (:group_id, 0, 1, 1)
            ON DUPLICATE KEY UPDATE posts_count = posts_count + 1, posts_visible = posts_visible + 1",
            array("group_id" => $groupId));

        $database->commit();
        return $postId;
    }

    static public function createAggregatedPost($database, $userId,
            $groupId, $feedTypeId, $objectId, $extraData) {
        // check for aggregated post
        $postId = $database->fetchColumn("SELECT post_id
            FROM feed_aggregations WHERE group_id = :group_id AND
                feed_type = :feed_type AND object_id = :object_id
                AND created > :time",
            array(
                "group_id" => $groupId,
                "feed_type" => $feedTypeId,
                "object_id" => $objectId,
                "time" => time() - self::FEED_AGGREGATION_TIME
            )
        );

        if ($postId) {
            // there is previous post so use it and update data
            $extraId = $database->fetchColumn("SELECT feed_extra_id FROM feed WHERE id = :post_id",
                array("post_id" => $postId));

            $database->exec("UPDATE feed_extra SET data = :data WHERE id = :extra_id",
                array("extra_id" => $extraId, "data" => json_encode($extraData)));
        }
        else {
            // create a new post since there is no aggregated post
            $feedExtraId = self::saveExtra($database, $extraData);
            $postId = self::createPost($database, $userId, $groupId, $feedTypeId, 0, 0, 0, $feedExtraId);

            // save new aggregation info
            $database->exec("INSERT INTO feed_aggregations (post_id, created, group_id, feed_type, object_id)
                VALUES (:post_id, :created, :group_id, :feed_type, :object_id)", array(
                "group_id" => $groupId,
                "feed_type" => $feedTypeId,
                "object_id" => $objectId,
                "created" => time(),
                "post_id" => $postId
            ));
        }

        return $postId;
    }

    static public function saveStatus($database, $message) {
        $database->exec("INSERT INTO feed_statuses(message)
            VALUES(:message)", array("message" => $message));

        return $database->lastInsertId();
    }

    static public function saveExtra($database, $data) {
        $database->exec("INSERT INTO feed_extra(data)
            VALUES(:data)", array("data" => json_encode($data)));

        return $database->lastInsertId();
    }

    private function postIsOwner($postId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_FEED, $postId);
    }

    private function postGetGroupId($postId) {
        return $this->objectGetGroupId(self::OBJECT_TYPE_FEED, $postId);
    }
}