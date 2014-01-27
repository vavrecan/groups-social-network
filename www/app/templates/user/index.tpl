{extends "../index.tpl"}
{block "title"}Groups Around Me - {$other_user.name|escape}{/block}
{block "main-content"}
    <div id="user-detail">
        <div class="sub-header">
            {include file="dialogs/user-report.tpl"}

            <ul class="menu">
                <li class="menu-profile-icon"><img src="{$other_user.image|escape}" width="25px" height="25px" /></li>
                <li><a href="{$base|escape}user-{$other_user.id|escape}" data-bind="click: function() { this.page('detail'); }">{$other_user.name|escape}</a></li>
                {if isset($other_user.distance)}
                <li><span class="menu-text">Distance {$other_user.distance|round:2|escape} miles</span></li>
                {/if}
                <li>
                    <span class="menu-text">
                    {if isset($other_user.location.country)}{$other_user.location.country.name|escape}{/if}
                    {if isset($other_user.location.region)} - {$other_user.location.region.name|escape}{/if}
                    {if isset($other_user.location.city)} - {$other_user.location.city.name|escape}{/if}
                    </span>
                </li>

                <li><span class="menu-text">Following: <span data-bind="text: following">{$other_user.following|escape}</span></span></li>
                <li><span class="menu-text">Followers: <span data-bind="text: followers">{$other_user.followers|escape}</span></span></li>
                <li><a data-bind="click: function() { this.page('groups'); }">Groups</a></li>

                {if isset($user)}
                <li><a data-bind="click: function() { this.page('activity'); }">Activity</a></li>
                <li><a data-bind="click: function() { this.page('messages'); }">Messages</a></li>
                {/if}
            </ul>

            {if isset($user)}
            <ul class="menu">
                <li data-bind="visible: isFollowed()"><a data-bind="click: unfollow">Unfollow</a></li>
                <li data-bind="visible: !isFollowed()"><a data-bind="click: follow">Follow</a></li>
                <li><a data-bind="click: function() { showDialog('#user-report'); }">Report</a></li>
            </ul>
            {/if}

        </div>

        <div data-bind="if: page() == 'detail'">
            <p data-bind="visible: isFollowed()">You are following this user</p>
            <p data-bind="visible: isFollowing()">You are followed by this user</p>

            <p>{$other_user.gender|escape}</p>
            <p>{$other_user.age|escape} years</p>
            <p>Created: {$other_user.created|escape}</p>
        </div>

        {literal}
        <div data-bind="if: page() == 'groups'">
            <div id="groups" data-bind="with: groups">
                <div class="groups">
                    <div data-bind="foreach: entries">

                        <div class="group-item" data-bind="css: {unread: unread}">
                            <div class="image">
                                <img data-bind="attr: {src: image}, visible: image != ''" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <a data-bind="text: title, attr: {href: link}"></a>
                                <div>
                                    <span data-bind="text: members"></span> members
                                </div>
                            </div>
                        </div>

                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">User is not member of any group</h2>
            </div>
        </div>

        <div data-bind="if: page() == 'messages'">
            <div data-bind="with: messages">

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
            <button data-bind="click: createConversation">Start conversation with this user</button>
        </div>


        <div data-bind="if: page() == 'activity'">
            <div data-bind="with: activity">
                <div data-bind="foreach: entries">

                    <div class="activity-item">
                        <div data-bind="text: time" class="time"></div>

                        <div data-bind="if: group" class="group">
                            <img data-bind="attr: {src: group.image}" height="25" width="25" />
                            <a data-bind="text: group.title, attr: {href: group.link}"></a>
                        </div>

                        <div data-bind="with: user" class="from">
                            <img data-bind="attr: {src: image}" height="25" width="25" />
                            <a data-bind="text: name, attr: {href: link}"></a>
                        </div>

                        <div class="text">
                            <span data-bind="text: activityText"></span>
                            <a data-bind="attr: {href: link}"><span data-bind="text: typeText"></span></a>
                        </div>
                    </div>

                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                <h2 data-bind="visible: !hasEntries() && !loading()">No activity yet.</h2>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        var user = {/literal}{$other_user|json_encode:$json_options}{literal};
        ko.applyBindings(new UserDetailModel(user), document.getElementById("user-detail"));
    </script>
    {/literal}
{/block}