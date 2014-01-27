{extends "../index.tpl"}
{block "title"}Groups Around Me - Conversations{/block}
{block "main-content"}
    {include file="dialogs/user-selector.tpl"}

    <h1>Conversations</h1>

    {literal}
        <div id="conversations">
            <div style="width: 250px; float: left; margin-right: 5px">
                <div data-bind="foreach: entries">

                    <div class="message-item" data-bind="css: {unread: !read()}, click: function() { $parent.showMessages(this); }">
                        <div data-bind="visible: memberCount() > 2" style="float:right;padding:5px"><span data-bind="text: memberCount"></span> members</div>
                        <div class="from" data-bind="with: user">
                            <img data-bind="attr: {src: image}" height="25" width="25" />
                            <a data-bind="text: name, attr: {href: link}"></a>
                        </div>
                        <div data-bind="text: message" class="message"></div>
                        <div data-bind="text: updated" class="time"></div>
                    </div>

                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                <h2 data-bind="visible: !hasEntries() && !loading()">No conversation</h2>
            </div>

            <div style="overflow: hidden">
                <div data-bind="with: messages">
                    <div>
                        <div data-bind="foreach: members">
                            <img data-bind="attr: {src: image}" height="25" width="25" />
                            <a data-bind="text: name, attr: {href: link}"></a>
                        </div>
                    </div>
                    <div>
                        <button data-bind="click: function() { extendConversation(); }">Add User to Conversation</button>
                        <button data-bind="click: function() { leaveConversation(); }">Leave Conversation</button>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    <div data-bind="foreach: entries">

                        <div class="message-item">
                            <div class="from" data-bind="with: user">
                                <img data-bind="attr: {src: image}" height="25" width="25" />
                                <a data-bind="text: name, attr: {href: link}"></a>
                            </div>
                            <div data-bind="text: message" class="message"></div>
                            <div data-bind="text: created" class="time"></div>

                            <div data-bind="visible: seen().length > 0" style="clear: both;padding: 5px;text-align: right;">
                                Seen by:
                                <span data-bind="foreach: seen">
                                    <a data-bind="text: name"></a>
                                </span>
                            </div>
                        </div>

                    </div>
                    <form data-bind="submit: sendMessage">
                        <input type="text" name="message" />
                        <input type="submit"/>
                    </form>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            ko.applyBindings(new ConversationsViewModel(), document.getElementById("conversations"));
        </script>
    {/literal}
{/block}