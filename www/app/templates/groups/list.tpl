{extends "../index.tpl"}
{block "title"}Groups Around Me - Groups{/block}
{block "main-content"}

    {literal}
        <div id="group-list">
            <ul class="menu" style="float: right;margin-left: 5px">
                {/literal}
                <li><a href="{$base|escape}groups/create">Create Group</a></li>
                <li><a href="{$base|escape}events">Attending events</a></li>
                {literal}
                <li data-bind="visible: type() == 'group_admins'">
                    <a href="" data-bind="click: function() { this.type('group_members'); }">Adminship</a>
                </li>
                <li data-bind="visible: type() == 'group_members'">
                    <a href="" data-bind="click: function() { this.type('group_admins'); }">Membership</a>
                </li>
            </ul>

            <h1>My groups</h1>

            <div class="groups">
                <div data-bind="foreach: entries">

                    <div class="group-item" data-bind="css: {unread: unread}">
                        <div class="image">
                            <img data-bind="attr: {src: image}, visible: image != ''" height="50" width="50" />
                        </div>

                        <div class="detail">
                            <a data-bind="text: title, attr: {href: link}"></a>
                            <div><span data-bind="text: postsCount"></span> posts, <span data-bind="text: members"></span> members</div>
                        </div>

                        <div class="unread">
                            <span data-bind="text: unreadCount"></span> new
                        </div>
                    </div>

                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
            </div>
            <h2 data-bind="visible: !hasEntries() && !loading()">
                <div data-bind="if: type() == 'group_admins'">You are not admin of any group</div>
                <div data-bind="if: type() == 'group_members'">You are not member of any group</div>
            </h2>
        </div>

        <script type="text/javascript">
            ko.applyBindings(new GroupsViewModel(true), document.getElementById("group-list"));
        </script>
    {/literal}

{/block}