/**
 * Simplify form sending
 * @param form
 * @param method
 * @param params
 * @param callback
 * @param koLoader
 * @param reset
 */
function submitForm(settings) {
    if (typeof(settings.reset) == "undefined")
        settings.reset = true;

    if (typeof(settings.koLoader) != "undefined") {
        if (settings.koLoader())
            return;
        settings.koLoader(true);
    }

    if (typeof(FormData) == "function") {
        var formData = new FormData(settings.form);

        // append passed params
        if (typeof(settings.params) != "undefined") {
            for (var i in settings.params) {
                formData.append(i, settings.params[i]);
            }
        }

        xhrRequest({
            "url": API_URL + settings.method,
            "formData": formData,
            "callback": function(response) {
                if (typeof(settings.koLoader) != "undefined")
                    settings.koLoader(false);

                if (typeof(response.error) != "undefined") {
                    showMessage("Error", response.error.message);
                    return;
                }

                if (settings.reset)
                    settings.form.reset();

                if (typeof(settings.callback) != "undefined")
                    settings.callback(response);
            }
        });
    }
    else {
        alert("Unsupported browser");
    }
}

/**
 * @param url
 * @param params
 * @param callback
 */
function xhrRequest(settings) {
    var formData = typeof(settings.formData) != "undefined" ? settings.formData : null;

    var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            // special for this tool - error 500
            if ((xhr.status >= 200 && xhr.status < 300) || xhr.status == 304 || xhr.status == 500)
            {
                var response = this.response || this.responseText;
                response = JSON.parse(response);

                if (typeof(settings.callback) != "undefined")
                    settings.callback(response);
            }
            else {
            }
        }
    };

    var params = settings.params;
    if (typeof(settings.params) == "object") {
        params = "";
        for (var i in settings.params) {
            // get value if function
            if (typeof(settings.params[i]) == "function")
                settings.params[i] = settings.params[i]();

            // append to string
            params = params + encodeURIComponent(i) + "=" + encodeURIComponent(settings.params[i]) + "&";
        }
    }

    // this should work with put too, but php has issues reading such file upload
    xhr.open(formData != null ? "POST" : "POST", settings.url, true);

    // this is needed for post, indeed
    if (settings.formData == null)
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

    xhr.send(formData != null ? formData : params);
    return xhr;
}

/**
 * Call api
 * @param method
 * @param params
 * @param callback
 * @param koLoader
 */
function api(method, params, callback, koLoader) {
    if (typeof(koLoader) != "undefined") {
        if (koLoader())
            return;
        koLoader(true);
    }

    xhrRequest({
        "url": API_URL + method,
        "params": params,
        "callback": function(response) {
            if (typeof(koLoader) != "undefined")
                koLoader(false);

            if (typeof(response.error) != "undefined") {
                showMessage("Error", response.error.message);
                return;
            }

            callback(response);
        }
    });
}

/**
 * Paging through class extension
 * @param self
 * @constructor
 */
function PagingViewModel(self) {
    self.method = "";
    self.params = {};

    self.offset = 0;
    self.limit = 10;
    self.untilId = "0";
    self.cursorMode = false;
    self.prepend = false;

    self.entryObject = Object;

    self.entries = ko.observableArray([]);
    self.hasMore = ko.observable(false);
    self.loading = ko.observable(false);
    self.hasEntries = ko.computed(function() {
        return self.entries().length > 0;
    });

    self.loadMore = function(callback) {
        // copy params over
        var params = {};
        for (var i in self.params)
            params[i] = self.params[i];

        if (self.cursorMode)
            params["until_id"] = self.untilId;
        else
            params["offset"] = self.offset;

        params["limit"] = self.limit;

        if (typeof(params.image_format) == "undefined")
            params["image_format"] = "p50x50";

        api(self.method, params, function(response) {
            var entries = response.data;

            for (var i = 0; i < entries.length; i++) {
                var object = new self.entryObject(entries[i], self);

                // insert proper object
                if (self.prepend)
                    self.entries.unshift(object);
                else
                    self.entries.push(object);

                if (self.cursorMode)
                    self.untilId = entries[i].id;
            }

            self.hasMore(response.has_more);

            if (!self.cursorMode)
                self.offset += self.limit;

            // call callback that we are done
            if (typeof(callback) == "function")
                callback();
        }, self.loading);
    };

    self.reload = function(callback) {
        self.offset = 0;
        self.entries.removeAll();
        self.untilId = "0";
        self.loadMore(callback);
    };
};

/**
 * Show message
 * @param title
 * @param message
 * @param callback
 * @param data
 * @param form
 */
var showMessage = function(title, message) {
    alert(message);
};

function GroupItemModel(item, parent) {
    var self = this;
    self.active = ko.observable(item.active == 1);
    self.id = item.id;
    self.title = item.title;
    self.userId = item.user_id;
    self.created = item.created;
    self.image = item.image;
    self.location = item.location;
    self.members = item.members;
    self.postsCount = item.posts_count;
    self.postsVisibleCount = item.posts_visible_count;
};

function GroupsLatestViewModel(parent) {
    var self = this;
    self.search = ko.observable("");
    PagingViewModel(self);
    self.entryObject = GroupItemModel;
    self.cursorMode = true;
    self.method = "/moderation/listLatest";
    self.params = {"moderation_session": parent.session, "latest_type": "groups", "time_format": "ago", "search": self.search};
    self.loadMore();

    self.search.subscribe(function(value) {
        self.reload();
    });
};

function UserItemModel(item, parent) {
    var self = this;
    self.active = ko.observable(item.active == 1);
    self.id = item.id;
    self.name = item.name;
    self.created = item.created;
    self.image = item.image;
    self.location = item.location;
};

function UsersLatestViewModel(parent) {
    var self = this;
    self.search = ko.observable("");
    PagingViewModel(self);
    self.entryObject = UserItemModel;
    self.cursorMode = true;
    self.method = "/moderation/listLatest";
    self.params = {"moderation_session": parent.session, "latest_type": "users", "time_format": "ago", "search": self.search};
    self.loadMore();

    self.search.subscribe(function(value) {
        self.reload();
    });
};

function GroupReportItemModel(item, parent) {
    var self = this;
    self.active = ko.observable(item.active == 1);
    self.id = item.id;
    self.message = item.message;
    self.groupId = item.group_id;
    self.userId = item.user_id;
    self.reporterUserId = item.reporter_user_id;
    self.title = item.title;
    self.created = item.created;
    self.image = item.image;
    self.members = item.members;
    self.postsCount = item.posts_count;
    self.postsVisibleCount = item.posts_visible_count;
};

function GroupsReportsViewModel(parent) {
    var self = this;
    PagingViewModel(self);
    self.entryObject = GroupReportItemModel;
    self.cursorMode = true;
    self.method = "/moderation/listReports";
    self.params = {"moderation_session": parent.session, "report_type": "groups", "time_format": "ago"};
    self.loadMore();

    self.deleteReport = function(report) {
        api("/moderation/deleteReport", {"moderation_session": parent.session, "report_type": "groups", "report_id": report.id}, function(response) {
            self.entries.remove(report);
        });
    };
};

function UserReportItemModel(item, parent) {
    var self = this;
    self.active = ko.observable(item.active == 1);
    self.id = item.id;
    self.message = item.message;
    self.userId = item.user_id;
    self.reporterUserId = item.reporter_user_id;
    self.name = item.name;
    self.created = item.created;
    self.image = item.image;
};

function UsersReportsViewModel(parent) {
    var self = this;
    PagingViewModel(self);
    self.entryObject = UserReportItemModel;
    self.cursorMode = true;
    self.method = "/moderation/listReports";
    self.params = {"moderation_session": parent.session, "report_type": "users", "time_format": "ago"};
    self.loadMore();

    self.deleteReport = function(report) {
        api("/moderation/deleteReport", {"moderation_session": parent.session, "report_type": "users", "report_id": report.id}, function(response) {
            self.entries.remove(report);
        });
    };
};

function GroupDetailViewModel(id, parent, caller) {
    var self = this;
    self.caller = caller;
    self.active = ko.observable();
    self.created = ko.observable();
    self.description = ko.observable();
    self.id = ko.observable();
    self.image = ko.observable();
    self.link = ko.observable();
    self.location = ko.observable();
    self.members = ko.observable();
    self.postsCount = ko.observable();
    self.postsVisibleCount = ko.observable();
    self.title = ko.observable();
    self.userId = ko.observable();
    self.webLink = WEB_URL + "/group-" + id;

    api("/moderation/groupDetail", {"moderation_session": parent.session, "group_id": id, "time_format": "ago", "image_format": "p50x50"}, function(response) {
        self.active(response.active == 1);
        self.created(response.created);
        self.description(response.description);
        self.id(response.id);
        self.image(response.image);
        self.link(response.link);
        self.location(response.location);
        self.members(response.members);
        self.postsCount(response.posts_count);
        self.postsVisibleCount(response.posts_visible_count);
        self.title(response.title);
        self.userId(response.user_id);
    });
};

function UserDetailViewModel(id, parent, caller) {
    var self = this;
    self.caller = caller;
    self.active = ko.observable();
    self.age = ko.observable();
    self.created = ko.observable();
    self.followers = ko.observable();
    self.following = ko.observable();
    self.gender = ko.observable();
    self.id = ko.observable();
    self.image = ko.observable();
    self.location = ko.observable();
    self.name = ko.observable();
    self.verified = ko.observable();
    self.webLink = WEB_URL + "/user-" + id;

    api("/moderation/userDetail", {"moderation_session": parent.session, "user_id": id, "time_format": "ago", "image_format": "p50x50"}, function(response) {
        self.active(response.active == 1);
        self.age(response.age);
        self.created(response.created);
        self.followers(response.followers);
        self.following(response.following);
        self.gender(response.gender);
        self.id(response.id);
        self.image(response.image);
        self.location(response.location);
        self.name(response.name);
        self.verified(response.verified == 1);
    });
};

function ModerationViewModel() {
    var self = this;
    self.logged = ko.observable(false);
    self.session = sessionStorage.getItem("moderation_session");
    self.email = ko.observable();

    self.previousSessionIp = ko.observable();
    self.previousSessionCity = ko.observable();
    self.previousSessionCountry = ko.observable();

    self.groupsReports = ko.observable();
    self.usersReports = ko.observable();
    self.groupsLatest = ko.observable();
    self.usersLatest = ko.observable();

    self.userDetail = ko.observable();
    self.groupDetail = ko.observable();

    self.page = ko.observable();
    self.page.subscribe(function(value) {
        if (value == "groups_latest") {
            self.groupsLatest(new GroupsLatestViewModel(self));
        }

        if (value == "users_latest") {
            self.usersLatest(new UsersLatestViewModel(self));
        }

        if (value == "groups_reports") {
            self.groupsReports(new GroupsReportsViewModel(self));
        }

        if (value == "users_reports") {
            self.usersReports(new UsersReportsViewModel(self));
        }
    });

    // methods
    self.verifyLogin = function() {
        api("/moderation/me", {"moderation_session": self.session}, function(data) {
            self.email(data.email);
            self.logged(true);
            sessionStorage.setItem("moderation_session", self.session);
            self.page("groups_latest");
        });
    };

    self.login = function(form) {
        submitForm({
            "form" : form,
            "method" : "/moderation/login",
            "reset": false,
            "callback": function(response) {
                self.previousSessionIp(response.previous_session_ip);
                self.previousSessionCity(response.previous_session_location.city);
                self.previousSessionCountry(response.previous_session_location.country);
                self.session = response.session;
                self.verifyLogin();
            }
        });
    };

    self.logout = function() {
        sessionStorage.setItem("moderation_session", '');
        self.logged(false);
        self.email(undefined);
    };

    self.showUserDetail = function(id, caller) {
        self.groupDetail(undefined);
        self.userDetail(new UserDetailViewModel(id, self, caller));
    };

    self.showGroupDetail = function(id, caller) {
        self.userDetail(undefined);
        self.groupDetail(new GroupDetailViewModel(id, self, caller));
    };

    // show overlay properly
    self.groupDetail.subscribe(function(value) {
        document.getElementById("overlay").style.display = (value == undefined) ? 'none' : 'block';
    });

    self.userDetail.subscribe(function(value) {
        document.getElementById("overlay").style.display = (value == undefined) ? 'none' : 'block';
    });

    self.deleteUser = function(user) {
        if (!confirm("Are you sure?"))
            return;

        api("/moderation/deleteUser", {"moderation_session": self.session, "user_id": user.id()}, function(response) {
            user.active(false);

            // set inactive on source caller
            if (typeof(user.caller) != "undefined")
                user.caller.active(false);
        });
    };

    self.deleteGroup = function(group) {
        if (!confirm("Are you sure?"))
            return;

        api("/moderation/deleteGroup", {"moderation_session": self.session, "group_id": group.id()}, function(response) {
            group.active(false);

            // set inactive on source caller
            if (typeof(group.caller) != "undefined")
                group.caller.active(false);
        });
    };

    // check session
    if (self.session && self.session != '') {
        self.verifyLogin();
    }
};

window.onload = function() {
    document.getElementById("overlay").onmouseup = function(e) {
        if (moderation.userDetail() != undefined)
            moderation.userDetail(undefined);

        if (moderation.groupDetail() != undefined)
            moderation.groupDetail(undefined);
    };

    var elements = document.getElementsByClassName("modal-detail");
    for (var i = 0; i < elements.length; i++) {
        elements[i].onmouseup =  function(e) {
            e.stopImmediatePropagation();
        };
    }
};