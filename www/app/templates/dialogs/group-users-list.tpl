<div style="display: none">
    <div id="group-users-list" class="dialog" data-bind="with: users">
        <h3 data-bind="visible: type() == 'members'">Members</h3>
        <h3 data-bind="visible: type() == 'admins'">Admins</h3>

        <div class="padding">

            <ul data-bind="visible: $root.canEdit()" class="menu">
                <li><span class="menu-text"><strong>Admin Menu</strong></span></li>
                <li data-bind="visible: type() == 'members'"><a data-bind="click: add">Add member</a></li>
                <li data-bind="visible: type() == 'admins'"><a data-bind="click: add">Add admin</a></li>
            </ul>

            {literal}
            <div class="user-list">
                <div class="users">
                    <div data-bind="foreach: entries">

                        <div class="item-name">
                            <img data-bind="attr: {src: image}" height="20" width="20" />
                            <a data-bind="text: name, attr: {href: link}"></a>
                            <div data-bind="text: created"></div>

                            <div data-bind="visible: $root.canEdit()">
                                <a data-bind="click: function() { $parent.remove(this) }">[x]</a>
                            </div>

                        </div>

                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">There are no members in this group</h2>
            </div>
            {/literal}

        </div>
    </div>
</div>