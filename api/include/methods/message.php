<?php

class Message extends LoggedUserMethodHandler
{
    public function createHandler() {
        $this->requireParam("conversation_id");
        $this->requireParam("message");

        $conversationId = $this->params["conversation_id"];
        $message = $this->params["message"];

        if (!$this->isMemberOfConversation($this->userId, $conversationId))
            throw new Exception("Not such conversation");

        if (strlen(trim($message)) == 0)
            throw new Exception("Message is empty");

        $this->database->exec("INSERT INTO messages (user_id, message, created, conversation_id)
            VALUES(:user_id, :message, :created, :conversation_id)",
            array(
                "user_id" => $this->userId,
                "message" => $message,
                "created" => time(),
                "conversation_id" => $conversationId
            )
        );

        $messageId = $this->database->lastInsertId();

        // update conversation
        $this->database->exec("UPDATE message_conversations
            SET last_user_id = :user_id, last_message_id = :message_id, updated = :time WHERE id = :conversation_id",
            array(
                "user_id" => $this->userId,
                "message_id" => $messageId,
                "conversation_id" => $conversationId,
                "time" => time()
            )
        );

        // broadcast to listeners
        $this->callSubscriptionsApi("trigger", array(
            "action" => "newMessage",
            "object" => $this->objectTypes[self::OBJECT_TYPE_CONVERSATION],
            "id" => $conversationId,
            "params" => array("message_id" => $messageId, "user_id" => $this->userId, "message" => $message)
        ));

        $this->response["message_id"] = $messageId;
        $this->response["success"] = 1;
        $this->output();
    }

    public function detailConversationHandler() {
        $this->requireParam("conversation_id");
        $conversationId = $this->params["conversation_id"];

        if (!$this->isMemberOfConversation($this->userId, $conversationId))
            throw new Exception("Not such conversation");

        $conversation = $this->database->fetch("SELECT
                message_conversations.id,
                message_conversations.updated,
                message_conversations.user_count as member_count,
                message_conversations.last_message_id,

                message_conversation_users.last_read_message_id,
                messages.message as message
            FROM message_conversation_users
                INNER JOIN message_conversations ON message_conversations.id = message_conversation_users.conversation_id
                LEFT JOIN messages ON messages.id = last_message_id
            WHERE
                message_conversation_users.user_id = :user_id AND
                message_conversation_users.conversation_id = :conversation_id",
            array(
                "conversation_id" => $conversationId,
                "user_id" => $this->userId
            )
        );

        if (!$conversation)
            throw new Exception("No such conversation");

        // find all members of this conversation
        $conversationMembers = $this->database->fetchAll("SELECT
                users.id, users.name, users.image
            FROM message_conversation_users
                INNER JOIN users ON users.id = message_conversation_users.user_id
            WHERE message_conversation_users.conversation_id = :conversation_id",
            array(
                "conversation_id" => $conversationId
            )
        );

        foreach ($conversationMembers as &$member) {
            // make last member as main user
            $conversation["user_id"] = $member["id"];
            $conversation["user_name"] = $member["name"];
            $conversation["user_image"] = $member["image"];

            $member["image"] = Utils::updateImageUrl($member["image"], $this->api->config["images_url"]);
        }

        $conversation["members"] = $conversationMembers;
        $this->formatConversationDetail($conversation);

        $this->response = $conversation;
        $this->output();
    }

    public function listConversationsHandler() {
        $limit = 10;
        $offset = 0;

        if (isset($this->params["limit"]))
            $limit = (int)$this->params["limit"];

        if (isset($this->params["offset"]))
            $offset = (int)$this->params["offset"];

        $nextPageLimit = $limit + 1;
        $hasMore = false;
        $conversations = $this->database->fetchAll("SELECT
                message_conversations.id,
                message_conversations.updated,
                message_conversations.user_count as member_count,

                message_conversations.last_message_id,
                message_conversation_users.last_read_message_id,

                users.id as user_id,
                users.name as user_name,
                users.image as user_image,

                messages.message as message
            FROM message_conversation_users
                INNER JOIN message_conversations ON message_conversations.id = message_conversation_users.conversation_id
                LEFT JOIN users ON users.id = (SELECT user_id FROM message_conversation_users
                    WHERE message_conversation_users.conversation_id = message_conversations.id
                    ORDER BY message_conversation_users.user_id = :user_id, message_conversation_users.created DESC LIMIT 1)
                LEFT JOIN messages ON messages.id = last_message_id
            WHERE message_conversation_users.user_id = :user_id
            ORDER BY message_conversations.updated DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "user_id" => $this->userId
            )
        );

        if (count($conversations) > $limit) {
            array_pop($conversations);
            $hasMore = true;
        }

        foreach ($conversations as &$conversation) {
            $this->formatConversationDetail($conversation);
        }

        $this->response["data"] = $conversations;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function listHandler() {
        $this->requireParam("conversation_id");
        $conversationId = $this->params["conversation_id"];

        if (!$this->isMemberOfConversation($this->userId, $conversationId))
            throw new Exception("Not such conversation");

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
        $messages = $this->database->fetchAll("SELECT
                messages.id,
                conversation_id,

                users.id as user_id,
                users.name as user_name,
                users.image as user_image,

                messages.message as message,
                messages.created as created
            FROM messages
                LEFT JOIN users ON users.id = messages.user_id
            WHERE
                conversation_id = :conversation_id
            " . ($sinceId > 0 ? " AND messages.id > {$sinceId} " : "")
              . ($untilId > 0 ? " AND messages.id < {$untilId} " : "").
            "ORDER BY messages.id DESC " . " LIMIT {$nextPageLimit} OFFSET {$offset}",
            array(
                "conversation_id" => $conversationId
            )
        );

        if (count($messages) > $limit) {
            array_pop($messages);
            $hasMore = true;
        }

        foreach ($messages as &$message) {
            $this->formatDetail($message);
        }

        // updated mark_as_read
        if (isset($this->params["mark_as_read"]) && $this->params["mark_as_read"] == 1) {
            $this->markAsRead($conversationId);
        }

        $this->response["data"] = $messages;
        $this->response["has_more"] = $hasMore;
        $this->output();
    }

    public function createConversationHandler() {
        $this->requireParam("user_id");
        $userId = $this->params["user_id"];

        $conversationId = $this->createConversation($userId);

        $this->response["conversation_id"] = (int)$conversationId;
        $this->output();
    }

    public function extendConversationHandler() {
        $this->requireParam("user_id");
        $this->requireParam("conversation_id");
        $userId = $this->params["user_id"];
        $conversationId = $this->params["conversation_id"];

        if (!$this->isMemberOfConversation($this->userId, $conversationId))
            throw new Exception("Not a member of conversation");

        if ($this->isMemberOfConversation($userId, $conversationId))
            throw new Exception("Already member");

        $this->addConversationMember($conversationId, $userId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function leaveConversationHandler() {
        $this->requireParam("conversation_id");
        $conversationId = $this->params["conversation_id"];

        if (!$this->isMemberOfConversation($this->userId, $conversationId))
            throw new Exception("Not a member of conversation");

        $this->removeConversationMember($conversationId, $this->userId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function markAsReadHandler() {
        $this->requireParam("conversation_id");
        $conversationId = $this->params["conversation_id"];

        $this->markAsRead($conversationId);

        $this->response["success"] = 1;
        $this->output();
    }

    private function markAsRead($conversationId) {
        $lastMessageId = $this->database->fetchColumn("SELECT id FROM messages WHERE
                conversation_id = :conversation_id ORDER BY messages.id DESC LIMIT 1",
            array(
                "conversation_id" => $conversationId
            )
        );

        $lastReadMessageId = $this->database->fetchColumn("SELECT last_read_message_id FROM message_conversation_users WHERE
                conversation_id = :conversation_id AND user_id = :user_id",
            array(
                "conversation_id" => $conversationId,
                "user_id" => $this->userId,
            )
        );

        // update if last read is not same as last message
        if ($lastReadMessageId != $lastMessageId) {
            $this->database->exec("UPDATE message_conversation_users SET last_read_message_id = :last_read_message_id WHERE
                conversation_id = :conversation_id AND user_id = :user_id",
                array(
                    "conversation_id" => $conversationId,
                    "user_id" => $this->userId,
                    "last_read_message_id" => $lastMessageId
                )
            );

            // notify that we read this message
            $this->callSubscriptionsApi("trigger", array(
                "action" => "seen",
                "object" => $this->objectTypes[self::OBJECT_TYPE_CONVERSATION],
                "id" => $conversationId,
                "params" => array("user_id" => $this->userId, "message_id" => $lastMessageId)
            ));
        }
    }

    private function formatConversationDetail(&$conversation) {
        $conversation["user"]["id"] = $conversation["user_id"];
        $conversation["user"]["image"] = Utils::updateImageUrl($conversation["user_image"], $this->api->config["images_url"]);
        $conversation["user"]["name"] = $conversation["user_name"];

        unset($conversation["user_id"]);
        unset($conversation["user_image"]);
        unset($conversation["user_name"]);

        $conversation["read"] = $conversation["last_message_id"] == $conversation["last_read_message_id"];

        unset($conversation["last_message_id"]);
        unset($conversation["last_read_message_id"]);

        $conversation["updated"] = Utils::updateTime($conversation["updated"], $this->api->config["time_format"]);
    }

    private function formatDetail(&$message) {
        $message["user"]["id"] = $message["user_id"];
        $message["user"]["image"] = Utils::updateImageUrl($message["user_image"], $this->api->config["images_url"]);
        $message["user"]["name"] = $message["user_name"];

        unset($message["user_id"]);
        unset($message["user_image"]);
        unset($message["user_name"]);

        $message["created"] = Utils::updateTime($message["created"], $this->api->config["time_format"]);
    }

    private function createConversation($userId) {
        // fresh conversation with 2 users
        $this->database->exec("INSERT INTO message_conversations(last_user_id, last_message_id, updated, user_count)
            VALUES(0, 0, :updated, 2)",
            array("updated" => time())
        );

        $conversationId = (int)$this->database->lastInsertId();

        // insert users to conversation
        $this->database->exec("INSERT INTO message_conversation_users(conversation_id, user_id, created)
            VALUES(:conversation_id, :user_id, :created),(:conversation_id, :other_user_id, :created)", array(
            "conversation_id" => $conversationId,
            "user_id" => $this->userId,
            "other_user_id" => $userId,
            "created" => time()
        ));

        // broadcast to listeners that a new conversation has been created
        $this->callSubscriptionsApi("trigger", array(
            "action" => "join",
            "object" => $this->objectTypes[self::OBJECT_TYPE_CONVERSATION],
            "id" => $conversationId,
            "params" => array("user_ids" => array($this->userId, $userId))
        ));

        return $conversationId;
    }

    private function addConversationMember($conversationId, $userId) {
        // insert users to conversation
        $this->database->exec("INSERT INTO message_conversation_users(conversation_id, user_id, created)
            VALUES(:conversation_id, :user_id, :created)", array(
            "conversation_id" => $conversationId,
            "user_id" => $userId,
            "created" => time()
        ));

        // update user count
        $this->database->exec("UPDATE message_conversations SET user_count = user_count + 1, updated = :updated WHERE id = :conversation_id", array(
            "conversation_id" => $conversationId,
            "updated" => time()
        ));

        // broadcast to listeners that a new conversation has been created
        $this->callSubscriptionsApi("trigger", array(
            "action" => "join",
            "object" => $this->objectTypes[self::OBJECT_TYPE_CONVERSATION],
            "id" => $conversationId,
            "params" => array("user_ids" => array($userId))
        ));
    }

    private function removeConversationMember($conversationId, $userId) {
        // remove user from conversation
        $this->database->exec("DELETE FROM message_conversation_users
            WHERE conversation_id = :conversation_id AND user_id = :user_id", array(
            "conversation_id" => $conversationId,
            "user_id" => $userId
        ));

        // update user count
        $this->database->exec("UPDATE message_conversations SET user_count = user_count - 1, updated = :updated WHERE id = :conversation_id", array(
            "conversation_id" => $conversationId,
            "updated" => time()
        ));

        // broadcast to listeners that a new conversation has been created
        $this->callSubscriptionsApi("trigger", array(
            "action" => "leave",
            "object" => $this->objectTypes[self::OBJECT_TYPE_CONVERSATION],
            "id" => $conversationId,
            "params" => array("user_ids" => array($userId))
        ));
    }

    private function isMemberOfConversation($userId, $conversationId) {
        $exists = $this->database->fetchColumn("SELECT COUNT(1) FROM  message_conversation_users WHERE conversation_id = :conversation_id AND user_id = :user_id",
            array("conversation_id" => $conversationId, "user_id" => $userId));

        if ($exists == 1)
            return true;

        return false;
    }
}