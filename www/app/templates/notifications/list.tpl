{extends "../index.tpl"}
{block "title"}Groups Around Me - Notifications{/block}
{block "main-content"}
    <h1>Notifications</h1>

    {literal}
        <div id="notifications">
            <div data-bind="foreach: entries">

                <div data-bind="css: {unread: !read}" class="notification-item">
                    <div data-bind="text: time" class="time"></div>

                    <div data-bind="if: group" class="group">
                        <img data-bind="attr: {src: group.image}" height="25" width="25" />
                        <a data-bind="text: group.title, attr: {href: group.link}"></a>
                    </div>

                    <div data-bind="with: user" class="from">
                        <img data-bind="attr: {src: image}" height="25" width="25" />
                        <a data-bind="text: name, attr: {href: link}"></a>
                        <span data-bind="if: $parent.otherUsersCount > 0" class="text">and <span data-bind="text: $parent.otherUsersCount"></span> other users</span>
                    </div>

                    <div class="text">
                        <span data-bind="text: notificationText"></span>
                        <a data-bind="attr: {href: link}, visible: type != ''"><span data-bind="text: typeText"></span></a>
                    </div>
                </div>

            </div>
            <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>

            <h2 data-bind="visible: !hasEntries() && !loading()">No notification</h2>
        </div>

        <script type="text/javascript">
            ko.applyBindings(new NotificationsViewModel(), document.getElementById("notifications"));
        </script>
    {/literal}
{/block}