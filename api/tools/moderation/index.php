<?php
$config = include("../../config.php");
$base = rtrim($config["base"], "/");
$siteUrl = "http://groupsaround.me";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Groups Around Me - Moderation</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <link rel="stylesheet" href="assets/css/style.css">

    <script type="text/javascript">
        var API_URL = <?php echo json_encode($base, JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS); ?>;
        var WEB_URL = <?php echo json_encode($siteUrl, JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS); ?>;
    </script>

    <script type="text/javascript" src="assets/js/knockout.js"></script>
    <script type="text/javascript" src="assets/js/models.js"></script>
</head>
<body>
    <div id="overlay"></div>
    <div>
        <h1>
            Moderation Tool
            <span data-bind="if: logged()" class="logged">
                <span data-bind="text: email"></span>
                <button data-bind="click: logout">Logout</button>
            </span>
        </h1>
        <div data-bind="if: !logged()">
            <h3>Login</h3>

            <form data-bind="submit: login">
                <div><input type="text" name="email" placeholder="email"/></div>
                <div><input type="password" name="password" placeholder="password"/></div>
                <div><input type="submit" value="login" /></div>
            </form>
        </div>

        <div data-bind="visible: userDetail() != undefined, with: userDetail" class="modal-detail">
            <div class="contents item">
                <button data-bind="click: function() { $parent.deleteUser(this) }" class="delete">Delete</button>
                <a data-bind="click: function() { $root.userDetail(undefined); }" class="close">&times;</a>
                <div class="image">
                    <img data-bind="attr: {src: image}" height="50" width="50" />
                </div>
                <div class="detail">
                    <div class="time">
                        <span data-bind="text: created"></span>
                    </div>
                    <h4><span data-bind="text: name"></span> <span data-bind="visible: !verified()">(unverified)</span> <span data-bind="visible: !active()">(deleted)</span></h4>
                    <div data-bind="with: location">
                        <span data-bind="if: this.country">
                        Location: <span data-bind="text: country.name"></span>
                        </span>
                        <span data-bind="if: this.region">
                        &bull; <span data-bind="text: this.region.name"></span>
                        </span>
                        <span data-bind="if: this.city">
                        &bull; <span data-bind="text: this.city.name"></span>
                        </span>
                    </div>
                    <div>
                        <span data-bind="text: gender"></span>
                    </div>
                    <div>
                        <span data-bind="text: age"></span> years
                    </div>
                    <div>
                        <span data-bind="text: followers"></span> followers,
                        <span data-bind="text: following"></span> following
                    </div>
                    <div>
                        <a data-bind="attr: {href: webLink}, text: webLink" target="_blank"></a>
                    </div>
                </div>

            </div>
        </div>

        <div data-bind="visible: groupDetail() != undefined, with: groupDetail" class="modal-detail">
            <div class="contents item">
                <button data-bind="click: function() { $parent.deleteGroup(this) }" class="delete">Delete</button>
                <a data-bind="click: function() { $root.groupDetail(undefined); }" class="close">&times;</a>
                <div class="image">
                    <img data-bind="attr: {src: image}" height="50" width="50" />
                </div>
                <div class="detail">
                    <div class="time">
                        <span data-bind="text: created"></span>
                    </div>
                    <h4><span data-bind="text: title"></span> <span data-bind="visible: !active()">(deleted)</span></h4>
                    <div>
                        <p data-bind="text: description"></p>
                    </div>
                    <div>
                        <p data-bind="text: link"></p>
                    </div>
                    <div>
                        <span data-bind="text: members"></span> members,
                        <span data-bind="text: postsVisibleCount"></span>/<span data-bind="text: postsCount"></span> posts
                    </div>
                    <div>
                        <a data-bind="click: function() { $root.showUserDetail(userId()); }">Owner profile</a>
                    </div>
                    <div data-bind="with: location">
                        <span data-bind="if: this.country">
                        Location: <span data-bind="text: this.country.name"></span>
                        </span>
                        <span data-bind="if: this.region">
                        &bull; <span data-bind="text: this.region.name"></span>
                        </span>
                        <span data-bind="if: this.city">
                        &bull; <span data-bind="text: this.city.name"></span>
                        </span>
                    </div>
                    <div>
                        <a data-bind="attr: {href: webLink}, text: webLink" target="_blank"></a>
                    </div>
                </div>
            </div>
        </div>

        <div data-bind="if: logged()">
            <div data-bind="visible: previousSessionIp()" class="previous-session">
                <h3>Previous session</h3>
                <div data-bind="text: previousSessionIp"></div>
                <div>
                    <span data-bind="text: previousSessionCity"></span> / <span data-bind="text: previousSessionCountry"></span>
                </div>
            </div>

            <ul class="pages">
                <li><a data-bind="click: function() { page('groups_latest'); }">New Groups</a></li>
                <li><a data-bind="click: function() { page('users_latest'); }">New Users</a></li>
                <li><a data-bind="click: function() { page('groups_reports'); }">Group Reports</a></li>
                <li><a data-bind="click: function() { page('users_reports'); }">User Reports</a></li>
            </ul>

            <div data-bind="if: page() == 'groups_latest'">
                <h3>New Groups</h3>
                <div data-bind="with: groupsLatest">
                    <input type="text" data-bind="value: search, valueUpdate: 'afterkeydown'" placeholder="Search" />
                    <div data-bind="foreach: entries">
                        <div class="item" data-bind="css: {inactive: !active()}">
                            <div class="image">
                                <img data-bind="attr: {src: image}" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <div class="time">
                                    <span data-bind="text: created"></span>
                                </div>
                                <h4><a data-bind="text: title, click: function() { $root.showGroupDetail(id, this); }"></a> <span data-bind="visible: !active()">(deleted)</span></h4>
                                <div>
                                    <span data-bind="text: members"></span> members,
                                    <span data-bind="text: postsVisibleCount"></span>/<span data-bind="text: postsCount"></span> posts
                                </div>
                                <div>
                                    <a data-bind="click: function() { $root.showUserDetail(userId); }">Owner profile</a>
                                </div>
                                <div>
                                    <span data-bind="if: location.country">
                                    Location: <span data-bind="text: location.country.name"></span>
                                    </span>
                                    <span data-bind="if: location.region">
                                    &bull; <span data-bind="text: location.region.name"></span>
                                    </span>
                                    <span data-bind="if: location.city">
                                    &bull; <span data-bind="text: location.city.name"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
            </div>

            <div data-bind="if: page() == 'users_latest'">
                <h3>New Users</h3>
                <div data-bind="with: usersLatest">
                    <input type="text" data-bind="value: search, valueUpdate: 'afterkeydown'" placeholder="Search" />
                    <div data-bind="foreach: entries">
                        <div class="item" data-bind="css: {inactive: !active()}">
                            <div class="image">
                                <img data-bind="attr: {src: image}" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <div class="time">
                                    <span data-bind="text: created"></span>
                                </div>
                                <h4><a data-bind="text: name, click: function() { $root.showUserDetail(id, this); }"></a> <span data-bind="visible: !active()">(deleted)</span></h4>
                                <div>
                                    <span data-bind="if: location.country">
                                    Location: <span data-bind="text: location.country.name"></span>
                                    </span>
                                    <span data-bind="if: location.region">
                                    &bull; <span data-bind="text: location.region.name"></span>
                                    </span>
                                    <span data-bind="if: location.city">
                                    &bull; <span data-bind="text: location.city.name"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
            </div>

            <div data-bind="if: page() == 'groups_reports'">
                <h3>Group Reports</h3>
                <div data-bind="with: groupsReports">
                    <div data-bind="foreach: entries">
                        <div class="item" data-bind="css: {inactive: !active()}">
                            <button data-bind="click: function() { $parent.deleteReport(this) }" class="delete">Delete</button>
                            <div class="image">
                                <img data-bind="attr: {src: image}" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <div class="time">
                                    <span data-bind="text: created"></span>
                                </div>
                                <h4><a data-bind="text: title, click: function() { $root.showGroupDetail(groupId, this); }"></a> <span data-bind="visible: !active()">(deleted)</span></h4>
                                <h3><span data-bind="text: message"></span></h3>
                                <div>
                                    <span data-bind="text: members"></span> members,
                                    <span data-bind="text: postsVisibleCount"></span>/<span data-bind="text: postsCount"></span> posts
                                </div>
                                <div>
                                    <a data-bind="click: function() { $root.showUserDetail(userId); }">Owner profile</a>
                                    &bull;
                                    <a data-bind="click: function() { $root.showUserDetail(reporterUserId); }">Reporter profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
            </div>

            <div data-bind="if: page() == 'users_reports'">
                <h3>User Reports</h3>
                <div data-bind="with: usersReports">
                    <div data-bind="foreach: entries">
                        <div class="item" data-bind="css: {inactive: !active()}">
                            <button data-bind="click: function() { $parent.deleteReport(this) }" class="delete">Delete</button>
                            <div class="image">
                                <img data-bind="attr: {src: image}" height="50" width="50" />
                            </div>
                            <div class="detail">
                                <div class="time">
                                    <span data-bind="text: created"></span>
                                </div>
                                <h4><a data-bind="text: name, click: function() { $root.showUserDetail(userId, this); }"></a> <span data-bind="visible: !active()">(deleted)</span></h4>
                                <h3><span data-bind="text: message"></span></h3>
                                <div>
                                    <a data-bind="click: function() { $root.showUserDetail(reporterUserId); }">Reporter profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        var moderation = new ModerationViewModel();
        ko.applyBindings(moderation);
    </script>
</body>
</html>