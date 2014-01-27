<?php

class Article extends LoggedUserMethodHandler
{
    public $publicMethods = array("detail", "list");

    public function listHandler() {
        $this->requireParam("group_id");

        $groupId = $this->params["group_id"];

        $isMember = $this->groupIsMember($groupId);
        $isAdmin = $this->groupIsMember($groupId);

        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $articles = $this->database->fetchAll("SELECT
                articles.id, articles.title, articles.created, articles.time, articles.visibility,
                users.id as user_id, users.name as user_name, users.image as user_image,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM articles
                INNER JOIN users ON users.id = articles.user_id
                LEFT JOIN comment_summary ON (comment_summary.object_id = articles.id AND comment_summary.type_id = :object_type_article)
                LEFT JOIN voting_summary ON (voting_summary.object_id = articles.id AND voting_summary.type_id = :object_type_article)
            WHERE articles.group_id = :group_id AND articles.active = 1 AND
                IF(:is_member = 1, 1, IF(articles.visibility = :visibility_public, 1, 0)) = 1
                ORDER BY articles.order" . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "group_id" => $groupId,
                "object_type_article" => self::OBJECT_TYPE_ARTICLE,
                "visibility_public" => self::VISIBILITY_PUBLIC,
                "is_member" => (int)$isMember
            )
        );

        if (count($articles) > $limit) {
            array_pop($articles);
            $hasMore = true;
        }

        foreach ($articles as &$article) {
            $this->formatDetail($article);

            if ($isAdmin)
                $article["can_edit"] = true;
        }

        $this->response["data"] = $articles;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function detailHandler() {
        $this->requireParam("article_id");
        $articleId = $this->params["article_id"];

        $article = $this->database->fetch("SELECT
                articles.id, articles.title, articles.contents, articles.created, articles.time, articles.group_id, articles.visibility,
                users.id as user_id, users.name as user_name, users.image as user_image,

                IFNULL(comment_summary.count,0) as comment_count,
                IFNULL(voting_summary.like,0) as like_count,
                IFNULL(voting_summary.dislike,0) as dislike_count
            FROM articles
                INNER JOIN users ON users.id = articles.user_id
                LEFT JOIN comment_summary ON (comment_summary.object_id = articles.id AND comment_summary.type_id = :object_type_article)
                LEFT JOIN voting_summary ON (voting_summary.object_id = articles.id AND voting_summary.type_id = :object_type_article)
            WHERE articles.id = :article_id AND articles.active = 1",
            array("article_id" => $articleId, "object_type_article" => self::OBJECT_TYPE_ARTICLE));

        if (!$article)
            throw new Exception("No such article");

        if ($article["visibility"] != self::VISIBILITY_PUBLIC)
            $this->groupRequireMember($article["group_id"]);

        $this->formatDetail($article);

        if ($this->groupIsAdmin($article["group_id"]))
            $article["can_edit"] = true;

        $this->response = $article;
        $this->output();
    }

    public function createHandler() {
        $this->requireParam("title");
        $this->requireParam("contents");
        $this->requireParam("group_id");

        $visibility = self::VISIBILITY_PRIVATE;

        // change visibility
        if (isset($this->params["visibility"]))
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);

        $title = $this->params["title"];
        $contents = Utils::sanitizeHtml($this->params["contents"]);
        $groupId = $this->params["group_id"];

        // check permissions
        if ($this->hasPrivacy($groupId, Group::GROUP_PRIVACY_ADMIN_ARTICLES_ONLY))
            $this->groupRequireAdmin($groupId);
        else
            $this->groupRequireMember($groupId);

        // check inputs
        if (empty($title) || strlen($title) < 2)
            throw new Exception("Title is too short");

        if (empty($contents))
            throw new Exception("Content is empty");

        // create group
        $this->database->exec("INSERT INTO articles(title, visibility, contents, active, created, user_id, group_id, time, `order`)
                                          VALUES (:title, :visibility, :contents, :active, :created, :user_id, :group_id, :created, 0)",
            array(
                "title" => $title,
                "visibility" => $visibility,
                "contents" => $contents,
                "active" => "1",
                "created" => time(),
                "user_id" => $this->userId,
                "group_id" => $groupId
            ));

        $articleId = $this->database->lastInsertId();
        $this->reorder($groupId, $articleId, 0);

        $this->logActivity(Activity::ACTIVITY_ARTICLE_CREATE, $groupId, self::OBJECT_TYPE_ARTICLE, $articleId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_ARTICLE, $articleId,
            $this->resolveAggregation($articleId, "create"));

        $this->response["article_id"] = $articleId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function deleteHandler() {
        $this->requireParam("article_id");

        $articleId = $this->params["article_id"];
        $groupId = $this->articleGetGroupId($articleId);

        // require owner or admin of the group
        if (!$this->articleIsOwner($articleId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $this->database->exec("UPDATE articles SET active = :active WHERE id = :article_id",
            array("active" => 0, "article_id" => $articleId));

        $this->logActivity(Activity::ACTIVITY_ARTICLE_DELETE, $groupId, self::OBJECT_TYPE_ARTICLE, $articleId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function updateHandler() {
        $this->requireParam("article_id");

        $articleId = $this->params["article_id"];
        $groupId = $this->articleGetGroupId($articleId);

        // require owner or admin of the group
        if (!$this->articleIsOwner($articleId) && !$this->groupIsAdmin($groupId))
            throw new Exception("No permission");

        $allowedColumns = array("title", "contents");

        if (isset($this->params["contents"]))
            $this->params["contents"] = Utils::sanitizeHtml($this->params["contents"]);

        foreach ($allowedColumns as $column) {
            if (isset($this->params[$column])) {
                $this->database->exec("UPDATE articles SET articles.{$column} = :value WHERE id = :article_id",
                    array("value" => $this->params[$column], "article_id" => $articleId));
            }
        }

        // update visibility
        if (isset($this->params["visibility"])) {
            $visibility = (int)array_search($this->params["visibility"], self::$visibilityTypes);
            $this->database->exec("UPDATE articles SET articles.visibility = :visibility WHERE id = :article_id",
                array("visibility" => $visibility, "article_id" => $articleId)
            );
        }

        // check if reorder is present
        if (isset($this->params["after_article_id"]))
            $this->reorder($groupId, $articleId, $this->params["after_article_id"]);

        // update modification time
        $this->database->exec("UPDATE articles SET articles.time = :time WHERE id = :article_id",
            array("time" => time(), "article_id" => $articleId));

        $this->logActivity(Activity::ACTIVITY_ARTICLE_UPDATE, $groupId, self::OBJECT_TYPE_ARTICLE, $articleId);
        $this->feedCreateAggregatedPost($groupId, Feed::FEED_TYPE_ARTICLE, $articleId,
            $this->resolveAggregation($articleId, "update"));

        $this->response["success"] = 1;
        $this->output();
    }

    private function reorder($groupId, $articleId, $afterArticleId = 0) {
        // get order
        $afterOrder = $this->database->fetchColumn("SELECT articles.order FROM articles WHERE
            articles.group_id = :group_id AND articles.id = :after_article_id",
            array("group_id" => $groupId, "after_article_id" => $afterArticleId));

        // insert after, before just in case we are pushing to start
        if ($afterOrder !== false)
            $afterOrder = $afterOrder + 1;
        else
            $afterOrder = 0;

        // check if some other article is blocking space
        $blocking = $this->database->fetchColumn("SELECT COUNT(1) FROM articles
            WHERE articles.group_id = :group_id AND articles.order = :after_order AND articles.id <> :article_id",
            array("after_order" => $afterOrder, "article_id" => $articleId, "group_id" => $groupId));

        if ($blocking > 0) {
            // make space for article - move all blocking articles forward
            $this->database->exec("UPDATE articles SET articles.order = articles.order + 1
                WHERE articles.group_id = :group_id AND articles.order >= :after_order",
                array(
                    "after_order" => $afterOrder,
                    "group_id" => $groupId
                )
            );
        }

        // set new order number
        $this->database->exec("UPDATE articles SET articles.order = :after_order
            WHERE group_id = :group_id AND articles.id = :article_id",
            array("after_order" => $afterOrder, "article_id" => $articleId, "group_id" => $groupId));
    }

    private function formatDetail(&$article) {
        $article["user"]["id"] = $article["user_id"];
        $article["user"]["image"] = Utils::updateImageUrl($article["user_image"], $this->api->config["images_url"]);
        $article["user"]["name"] = $article["user_name"];

        unset($article["user_id"]);
        unset($article["user_name"]);
        unset($article["user_image"]);

        if (array_key_exists("visibility", $article)) {
            $article["visibility"] = self::$visibilityTypes[$article["visibility"]];
        }

        $article["created"] = Utils::updateTime($article["created"], $this->api->config["time_format"]);
        $article["time"] = Utils::updateTime($article["time"], $this->api->config["time_format"]);

        $article["can_interact"] = $this->userId != null;
        $article["can_edit"] = (int)$article["user"]["id"] == (int)$this->userId;
    }

    private function resolveAggregation($articleId, $action) {
        $article = $this->database->fetch("SELECT title FROM articles
            WHERE id = :article_id", array("article_id"=> $articleId));

        return $article + array(
            "type" => $this->objectTypes[self::OBJECT_TYPE_ARTICLE],
            "id" => $articleId,
            "action" => $action
        );
    }

    private function articleIsOwner($articleId) {
        return $this->objectIsOwner(self::OBJECT_TYPE_ARTICLE, $articleId);
    }

    private function articleGetGroupId($articleId) {
        return $this->objectGetGroupId(self::OBJECT_TYPE_ARTICLE, $articleId);
    }
}