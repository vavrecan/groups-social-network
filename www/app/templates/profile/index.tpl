{extends "../index.tpl"}
{block "title"}Groups Around Me - Profile{/block}
{block "main-content"}
    <div id="profile">
        <ul class="menu">
            <li><a data-bind="click: function() { this.page('detail'); }">Profile</a></li>
            <li><a data-bind="click: function() { this.page('following'); }">Following: <span data-bind="text: followingCount"></span></a></li>
            <li><a data-bind="click: function() { this.page('followers'); }">Followers: <span data-bind="text: followersCount"></span></a></li>
            <li><a data-bind="click: function() { this.page('requests'); }">Group Requests</a></li>
        </ul>

        <div data-bind="if: page() == 'detail'">
            <h1>Logout</h1>
                <a href="{$base|escape}auth/logout">Logout</a>
            <h1>Profile</h1>
            <form data-bind="submit: update">
                <div class="row">
                    <input name="first_name" class="text" placeholder="First Name" type="text" value="{$user.first_name|escape}" />
                    <input name="last_name" class="text" placeholder="Last Name" type="text" value="{$user.last_name|escape}" />
                </div>
                <div class="row">
                    <strong>Date of Birth</strong>
                </div>

                <input data-bind="value: birthday" name="birthday" value="{$user.birthday.day}.{$user.birthday.month}.{$user.birthday.year}" type="hidden" />

                <div class="row">
                    <select name="month" data-bind="value: birthdayMonth">
                        {foreach from=$months item=month_name key=month_id}
                            <option value="{$month_id|escape}">{$month_name|escape}</option>
                        {/foreach}
                    </select>
                    <select name="day" data-bind="value: birthdayDay">
                        {foreach from=$days item=day}
                            <option value="{$day|escape}">{$day|escape}</option>
                        {/foreach}
                    </select>
                    <select name="year" data-bind="value: birthdayYear">
                        {foreach from=$years item=year name=years}
                            <option value="{$year|escape}">{$year|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="row">
                    <select name="gender" class="gender-select">
                        <option value="">Select your gender</option>
                        {foreach from=$genders key=gender_id item=gender_name}
                            <option value="{$gender_id|escape}" {if $gender_id == $user.gender}selected="selected"{/if}>{$gender_name|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <input type="submit" class="button" value="Save" />
            </form>

            <h3>Profile Image</h3>
            <form data-bind="submit: setImage">
                <div class="row">
                    <input name="image" class="text" placeholder="Select an image" type="file" />
                </div>
                <input type="submit" class="button" value="Save" />
            </form>

            <form data-bind="submit: removeImage">
                <input type="submit" class="button" value="Remove" />
            </form>

            <h3>Change Password</h3>
            <form data-bind="submit: changePassword">
                <div class="row">
                    <input name="password" class="text" placeholder="Password" type="password" />
                </div>
                <div class="row">
                    <input name="password_again" class="text" placeholder="Re-enter Password" type="password" />
                </div>
                <input type="submit" class="button" value="Save" />
            </form>

            <h3>Set Location</h3>
            <form data-bind="submit: updateLocation">
                <div class="row">
                    <input name="latitude" class="text" placeholder="Latitude" type="text" value="{$user.location.latitude|escape}" />
                </div>
                <div class="row">
                    <input name="longitude" class="text" placeholder="Longitude" type="text" value="{$user.location.longitude|escape}" />
                </div>
                <input type="submit" class="button" value="Save" />
            </form>
        </div>

        {literal}
        <div data-bind="if: page() == 'following'">
            <div data-bind="with: following">
                <div class="users">
                    <div data-bind="foreach: entries">

                        <div class="user-item">
                            <div class="image">
                                <img data-bind="attr: {src: image}, visible: image != ''" height="35" width="35" />
                            </div>
                            <div class="detail">
                                <a data-bind="text: name, attr: {href: link}"></a>
                            </div>
                            <button data-bind="visible: !isFollowed(), click: function() { this.parent.parent.follow(this) }">Follow</button>
                        </div>

                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">You are not following anybody</h2>
            </div>
        </div>

        <div data-bind="if: page() == 'followers'">
            <div data-bind="with: followers">
                <div class="users">
                    <div data-bind="foreach: entries">

                        <div class="user-item">
                            <div class="image">
                                <img data-bind="attr: {src: image}, visible: image != ''" height="35" width="35" />
                            </div>
                            <div class="detail">
                                <a data-bind="text: name, attr: {href: link}"></a>
                            </div>
                            <button data-bind="visible: !isFollowed(), click: function() { this.parent.parent.follow(this) }">Follow</button>
                        </div>

                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">You have no followers</h2>
            </div>
        </div>

        <div data-bind="if: page() == 'requests'">
            <div data-bind="with: requests">
                <div class="users">
                    <div data-bind="foreach: entries">

                        <div class="request-item">
                            <div class="text">
                                <div>
                                    <span data-bind="visible: type == 'request_admin'">Admin</span>
                                    <span data-bind="visible: type == 'request_member'">Member</span>
                                </div>
                                <div data-bind="text: created"></div>
                            </div>
                            <div class="image">
                                <img data-bind="attr: {src: group.image}, visible: group.image != ''" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <a data-bind="text: group.title, attr: {href: group.link}"></a>
                            </div>
                            <button data-bind="click: function() { $parent.cancel(this) }">Decline</button>
                            <button data-bind="click: function() { $parent.accept(this) }">Accept</button>
                            <div class="from">
                                <img data-bind="attr: {src: user.image}, visible: user.image != ''" height="25" width="25" />
                                <a data-bind="text: user.name, attr: {href: user.link}"></a>
                            </div>
                        </div>

                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">You have no pending requests</h2>
            </div>
        </div>

    </div>

    <script type="text/javascript">
        ko.applyBindings(new ProfileDetailModel(window.user), document.getElementById("profile"));
    </script>
    {/literal}
{/block}