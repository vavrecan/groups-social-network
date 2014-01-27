/**
 * Serialize form into object map
 * @param form
 * @returns {"name": "value", ...}
 */
function serializeForm(form) {
    var params = {};
    for (var i = 0; i < form.elements.length; i++) {
        if (typeof(form.elements[i]) == "object" && form.elements[i].name != "") {
            // ignore unchecked
            if ((form.elements[i].type == "checkbox" || form.elements[i].type == "radio") && !form.elements[i].checked)
                continue;

            params[form.elements[i].name] = form.elements[i].value;
        }
    }
    return params;
}

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
    // internal methods
    var init = function() {
        if (typeof(settings.reset) == "undefined")
            settings.reset = true;

        if (typeof(settings.koLoader) != "undefined") {
            if (settings.koLoader())
                return;
            settings.koLoader(true);
        }
    };

    var finish = function(response) {
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
    };

    var sendUsingFormData = function() {
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
                finish(response);
            }
        });
    };

    var sendUsingIframe = function() {
        var id = "call" + Math.floor(Math.random() * 10000000);

        var iframe = document.createElement("iframe");
        iframe.setAttribute("id", id);
        iframe.setAttribute("name", id);
        iframe.setAttribute("style", "display:none");

        var form = settings.form;
        form.setAttribute("method", "post");
        form.setAttribute("action", API_URL + settings.method);
        form.setAttribute("enctype", "multipart/form-data");
        form.setAttribute("target", id);
        form.appendChild(iframe);

        var tempInputs = [];
        // append passed params
        if (typeof(settings.params) != "undefined") {
            for (var i in settings.params) {
                var input = document.createElement("input");
                input.setAttribute("type", "hidden");
                input.setAttribute("name", i);
                input.setAttribute("value", settings.params[i]);
                form.appendChild(input);
                tempInputs.push(input);
            }
        }

        var iframeOnLoad = function() {
            // read body to get response
            var contentBody = this.contentDocument.body;
            var response = JSON.parse(typeof(contentBody.innerText) != "undefined" ? contentBody.innerText : contentBody.textContent);

            // finish it
            finish(response);

            // cleanup
            form.removeChild(iframe);
            for (var i = 0; i < tempInputs.length; i++)
                form.removeChild(tempInputs[i]);
        };

        if (iframe.attachEvent)
            iframe.attachEvent("onload", function() { iframeOnLoad.call(iframe); });
        else if(iframe.addEventListener)
            iframe.addEventListener("load", iframeOnLoad, false);

        settings.form.submit();
    };

    var sendUsingAjax = function() {
        if (typeof(settings.params) == "undefined")
            settings.params = {};

        var array = serializeForm(settings.form);
        for (var i in array) {
            settings.params[i] = array[i];
        }

        xhrRequest({
            "url": API_URL + settings.method,
            "params": settings.params,
            "callback": function(response) {
                finish(response);
            }
        });
    }

    var hasFile = function() {
        var form = settings.form;
        for (var i = 0; i < form.elements.length; i++) {
            if (typeof(form.elements[i]) == "object" && form.elements[i].name != "" && form.elements[i].type == "file")
                return true;
        }
        return false;
    };

    init();

    if (typeof(FormData) == "function") {
        sendUsingFormData();
    }
    else if (hasFile()) {
        sendUsingIframe();
    }
    else {
        sendUsingAjax();
    }
}

/**
 * Raw AJAX call
 * @param url
 * @param params
 * @param callback
 */
function xhrRequest(settings) {
    var formData = typeof(settings.formData) != "undefined" ? settings.formData : null;

    var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if ((xhr.status >= 200 && xhr.status < 300) || xhr.status == 304)
            {
                var response = this.response || this.responseText;
                response = JSON.parse(response);

                if (typeof(settings.callback) != "undefined")
                    settings.callback(response);
            }
            else {
                // error handler
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
 * Get link url for given object
 * @param item
 */
function resolveLinkDetail(item) {
    var link = false;

    if (typeof(item.group) == "object" && item.group.hasOwnProperty("id")) {
        link = BASE_PATH + "group-" + item.group.id;

        if (item.type == "post" || item.type == "article" || item.type == "event" || item.type == "gallery" || item.type == "comment") {
            link = link + "/" + item.type + "-" + item.object_id;
        }
        else if (item.type == "gallery_image") {
            link = link + "/gallery-image-" + item.object_id;
        }
    }

    if (item.type == "user") {
        link = BASE_PATH + "user-" + item.object_id;
    }

    return link;
}

/**
 * Simple hash tag manipulation
 * @param method
 * @param params
 * @param callback
 * @param koLoader
 */
function setHashtagPage(page) {
    window.location.hash = "#page-" + page;
};

function getHashtagPage() {
    var hash = window.location.hash;
    var match = hash.match(/^\#page\-(.*)$/i);
    if (match != null && match.length > 1) {
        return match[1];
    }
    return false;
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
 * User selector dialog with model
 */
function selectUser(callback) {
    if (typeof(window.userSelectModel) == "undefined")
        throw new Error("User select not initialized");

    window.userSelectModel.callback = callback;
    window.userSelectModel.reload();
    showDialog("#user-selector");
}

function UserSelectViewModel() {
    var self = this;
    PagingViewModel(self);

    self.name = ko.observable("");
    self.params = {"name": self.name, "image_format": "p50x50", "time_format": "day"};
    self.entryObject = UserItemModel;
    self.method = "/user/listFollowing";
    self.callback = function (user) {};

    self.name.subscribe(function(name) {
        self.reload();
    });

    self.searchEmail = function(form) {
        submitForm({
            "form" : form,
            "method" : "/user/searchEmail",
            "reset": false,
            "callback": function(response) {
                var user = new UserItemModel(response);
                self.select(user);
            }
        });
    };

    self.select = function(user) {
        self.callback(user);
    }
};

/**
 * This class extension allow paging through view model
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
 * Load country, region, city select extension
 * @param self
 * @constructor
 */
function LocationViewModel(self) {
    self.distance = ko.observable(10);
    self.countryId = ko.observable("");
    self.regionId = ko.observable("");
    self.cityId = ko.observable("");

    self.countries = ko.observableArray([]);
    self.regions = ko.observableArray([]);
    self.cities = ko.observableArray([]);

    self.loadCountries = function() {
        self.countries.removeAll();
        self.countries.push({"id": "", "name": "Select Country"});

        api("/location/listCountries", {}, function(response) {
            var entries = response.data;
            for (var i = 0; i < entries.length; i++) {
                self.countries.push(entries[i]);
            }
        });
    };

    self.loadRegions = function(countryCode) {
        self.regions.removeAll();
        self.regions.push({"id": "", "name": "Select Region"});

        api("/location/listRegions", {"country_code": countryCode}, function(response) {
            var entries = response.data;
            for (var i = 0; i < entries.length; i++) {
                self.regions.push(entries[i]);
            }
        });
    };

    self.loadCities = function(countryCode, regionCode) {
        self.cities.removeAll();
        self.cities.push({"id": "", "name": "Select City"});

        api("/location/listCities", {"country_code": countryCode, "region_code": regionCode}, function(response) {
            var entries = response.data;
            for (var i = 0; i < entries.length; i++) {
                self.cities.push(entries[i]);
            }
        });
    };

    self.countryCode = function(countryId) {
        var countries = self.countries();
        for (var i = 0; i < countries.length; i++) {
            if (countries[i].id == countryId)
                return countries[i].country_code;
        }

        return "";
    };

    self.regionCode = function(regionId) {
        var regions = self.regions();
        for (var i = 0; i < regions.length; i++) {
            if (regions[i].id == regionId)
                return regions[i].region_code;
        }

        return "";
    };

    self.countryId.subscribe(function(countryId) {
        self.regionId("");
        self.regions.removeAll();
        if (typeof(countryId) != "undefined" && countryId != "") {
            self.distance("");

            var countryCode = self.countryCode(countryId);
            self.loadRegions(countryCode);
            return;
        }
    });

    self.regionId.subscribe(function(regionId) {
        self.cityId("");
        self.cities.removeAll();

        if (typeof(regionId) != "undefined" && regionId != "") {
            var countryCode = self.countryCode(self.countryId());
            var regionCode =  self.regionCode(regionId);

            self.loadCities(countryCode, regionCode);
            return;
        }
    });

    self.cityId.subscribe(function(cityId) {
    });

    self.distance.subscribe(function(distance) {
        if (typeof(distance) != "undefined" && distance != "") {
            self.countryId("");
            return;
        }
    });
};

/**
 * Class extension that allows detail loading and HTML 5 history handing
 * Used on events, articles, galleries
 *
 * settable parameters
 * detailMethod
 * detailParamsCallback
 * detailModel
 * detailCallback
 * detailUrlCallback
 */
function LoadDetailViewModel(self) {
    self.detailMethod = "";
    self.detailParamsCallback = function(item) { return {}; };
    self.detailModel = Object;
    self.detailCallback = function(model) {};
    self.detailUrlCallback = function(item) { return BASE_PATH; };

    self.load = function(item) {
        if (typeof(item) != "object") {
            api(self.detailMethod, self.detailParamsCallback(item), function(response) {
                self.load(response);
            });
        }
        else {
            var model = new self.detailModel(item, self);
            self.detailCallback(model);
        }
    };

    self.select = function(item) {
        // save history
        if (window.history.pushState) {
            window.history.pushState(item, null, self.detailUrlCallback(item));
        }

        self.load(item);
    };

    // restore history
    if (window.history.pushState) {
        var loadState = function(e) {
            if (e.state == null) return;
            self.load(e.state);
        };

        if (window.attachEvent)
            window.attachEvent("onpopstate", function() { loadState.call(window); });
        else if(window.addEventListener)
            window.addEventListener("popstate", loadState, false);
    }
}

/**
 * Voting
 */
function VoteItemModel(item) {
    var self = this;
    self.created = item.created;
    self.user = false;

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }
};

function VotingListViewModel() {
    var self = this;
    self.objectType = ko.observable();
    self.objectId = ko.observable();

    PagingViewModel(self);
    self.votingType = ko.observable("like");
    self.entryObject = VoteItemModel;
    self.method = "/voting/list";
    self.params = {"voting_type": self.votingType, "object_id": self.objectId, "type": self.objectType, "time_format": "ago"};
};

function VotingViewModel(parent, objectType, objectId, likeCount, dislikeCount) {
    var self = this;
    self.canInteract = parent.canInteract;
    self.objectType = ko.observable(objectType);
    self.objectId = ko.observable(objectId);
    self.likesCount = ko.observable(parseInt(likeCount));
    self.dislikesCount = ko.observable(parseInt(dislikeCount));

    self.likeText = ko.computed(function() {
        return self.likesCount() > 0 ? self.likesCount() + " like" + (self.likesCount() > 1 ? "s" : "") : "Like";
    });

    self.dislikeText = ko.computed(function() {
        return self.dislikesCount() > 0 ? self.dislikesCount() + " dislike" + (self.dislikesCount() > 1 ? "s" : "") : "Dislike";
    });

    self.like = function() {
        api("/voting/vote", {"object_id": self.objectId, "type": self.objectType, "voting_type": "like"}, function(response) {
            self.likesCount(self.likesCount() + 1);
        });
    };

    self.dislike = function() {
        api("/voting/vote", {"object_id": self.objectId, "type": self.objectType, "voting_type": "dislike"}, function(response) {
            self.dislikesCount(self.dislikesCount() + 1);
        });
    };

    self.showLikes = function() {
        // find voting model
        window.votingDetail.objectType(self.objectType());
        window.votingDetail.objectId(self.objectId());
        window.votingDetail.votingType("like");
        window.votingDetail.reload();
        showDialog("#voting-detail");
    };

    self.showDislikes = function() {
        // find voting model
        window.votingDetail.objectType(self.objectType());
        window.votingDetail.objectId(self.objectId());
        window.votingDetail.votingType("dislike");
        window.votingDetail.reload();
        showDialog("#voting-detail");
    };
};

/**
 * Comments
 */
function CommentItemModel(item, parent) {
    var self = this;
    self.canInteract = parent.canInteract;
    self.id = item.id;
    self.canEdit = item.can_edit;
    self.message = item.message;
    self.created = item.created;
    self.user = false;
    self.voting = new VotingViewModel(self, "comment", self.id, item.like_count, item.dislike_count);

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }
};

function CommentsViewModel(parent, objectType, objectId, commentCount) {
    var self = this;
    self.canInteract = parent.canInteract;
    self.objectType = objectType;
    self.objectId = objectId;
    self.commentsCount = ko.observable(parseInt(commentCount));

    PagingViewModel(self);
    self.entryObject = CommentItemModel;
    self.method = "/comment/list";
    self.params = {"object_id": self.objectId, "type": self.objectType, "time_format": "ago"};
    self.cursorMode = true;
    self.prepend = true;
    self.limit = 5;
    self.show = ko.observable(false);
    self.posting = ko.observable(false);

    self.commentText = ko.computed(function() {
        return self.commentsCount() > 0 ? self.commentsCount() + " comment" + (self.commentsCount() > 1 ? "s" : "") : "Comment";
    });

    self.preload = function() {
        var previousLimit = self.limit;
        self.limit = 2;
        self.loadMore();
        self.limit = previousLimit;
        self.show(true);
    };

    self.comment = function() {
        if (self.show()) {
            self.show(false);
        }
        else {
            self.show(true);

            if (self.untilId == "0")
                self.loadMore();
        }
    };

    self.load = function(commentId) {
        api("/comment/detail", {"comment_id": commentId, "image_format": "p50x50", "time_format": "ago"}, function(response) {
            var entry = response;
            self.entries.push(new CommentItemModel(entry, self));

            // make sure we do not show it again
            if (self.untilId == "0")
                self.untilId = commentId;
        }, self.loading);
    };

    self.submit = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/comment/post",
            "params": {"object_id": self.objectId, "type": self.objectType},
            "callback": function(response) {
                self.load(response.comment_id);
                self.commentsCount(self.commentsCount() + 1);
            }
        });
    };

    self.remove = function(comment) {
        if (!confirm("Are you sure that you want to remove this comment?"))
            return;

        api("/comment/delete", {"comment_id": comment.id }, function(response) {
            self.entries.remove(comment);
            self.commentsCount(self.commentsCount() - 1);
        }, self.loading);
    };
};



function FeedLinkItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.description = item.description;
    self.host = item.host;
    self.image = item.image;
    self.title = item.title;
    self.url = item.url;
};

function FeedItemExtraModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.title = item.title;
    self.type = item.type;
    self.link = false;

    if (self.type == "article" || self.type == "event" || self.type == "gallery")
        self.link = BASE_PATH + "group-" + parent.feedViewModel.group.id + "/" + self.type + "-" + self.id;
};

function FeedItemModel(item, parent) {
    var self = this;
    self.feedViewModel = parent;
    self.id = item.id;
    self.canEdit = item.can_edit;
    self.canInteract = item.can_interact;
    self.type = item.feed_type;
    self.message = item.status != null && item.status.hasOwnProperty("message") ? item.status.message : "";
    self.extra = item.extra != null && item.extra ? new FeedItemExtraModel(item.extra, self) : false;
    self.created = item.created;

    self.objectType = "post";
    self.objectId = self.id;
    self.voting = new VotingViewModel(self, self.objectType, self.objectId, item.like_count, item.dislike_count);
    self.comments =  new CommentsViewModel(self, self.objectType, self.objectId, item.comment_count);

    self.read = (item.read_status == "read");
    self.visibility = ko.observable(item.visibility);
    self.linkDetail = item.link;
    self.image = item.status != null && item.status.hasOwnProperty("message") ? item.status.image : "";
    self.imageFull = item.status != null && item.status.hasOwnProperty("message") ? item.status.image_full : "";
    self.link = BASE_PATH + "group-" + parent.group.id + "/post-" + self.id;

    self.user = false;
    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }
};

function FeedViewModel(groupViewModel) {
    var self = this;
    PagingViewModel(self);
    self.group = groupViewModel;

    self.entryObject = FeedItemModel;
    self.method = "/feed/list";
    self.params = {"group_id": self.group.id, "mark_as_read": 1, "image_format": "p50x50", "time_format": "ago"};
    self.cursorMode = true;
    self.posting = ko.observable(false);
    self.imageDetail = ko.observable();

    self.attachedUrlLoading = ko.observable(false);
    self.attachedUrl = "";
    self.attachedUrlDetail = ko.observable();

    self.showAddPost = ko.observable(false);
    self.canPost = ko.computed(function() {
        if (self.group.privacy().indexOf("admin_posts_only") != -1 && !self.group.canEdit())
            return false;
        return self.group.isMember();
    });

    self.group.isMember.subscribe(function(isMember) {
        // reload list on subscribe change
        self.reload();
    });

    self.load = function(postId) {
        api("/feed/detail", {"post_id": postId, "image_format": "p50x50", "time_format": "ago" }, function(response) {
            var entry = response;
            self.entries.unshift(new FeedItemModel(entry, self));
        }, self.loading);
    };

    self.checkLink = function(text) {
        var url = null;
        var matches = text.match(/https?:\/\/\S+/i);
        if (matches == null) {
            matches = text.match(/www\.\S+?\.\S+/i);
            if (matches != null)
                url = "http://" + matches[0];
        }
        else {
            url = matches[0];
        }

        if (url == self.attachedUrl) {
            return;
        }

        if (url == null) {
            self.attachedUrl = "";
            self.attachedUrlDetail(undefined);
            self.attachedUrlLoading(false);
            return;
        }

        self.attachedUrl = url;
        self.attachedUrlLoading(true);

        api("/feed/linkDetail", {"url": url, "image_format": "p50x50", "time_format": "ago" }, function(response) {
            self.attachedUrlDetail(new FeedLinkItemModel(response, self));
            self.attachedUrlLoading(false);
        }, self.posting);
    };

    self.submit = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/feed/post",
            "params": { "group_id" : self.group.id },
            "callback": function(response) {
                self.load(response.post_id);
                self.checkLink("");
            }
        });
    };

    self.remove = function(post) {
        if (!confirm("Are you sure that you want to remove this feed post?"))
            return;

        api("/feed/delete", {"post_id": post.id }, function(response) {
            self.entries.remove(post);
        }, self.loading);
    };

    self.changeVisibility = function(post, visibility) {
        api("/feed/setVisibility", {"post_id": post.id, "visibility": visibility }, function(response) {
            post.visibility(visibility);
        }, self.loading);
    };

    self.showImage = function(post) {
        self.imageDetail(post.imageFull);
        showDialog("#image-detail");
    };

    self.loadMore();
};

function PostViewModel(groupViewModel, item) {
    var self = this;
    self.group = groupViewModel;
    self.post = new FeedItemModel(item, self);
    self.imageDetail = ko.observable();

    self.showImage = function(post) {
        self.imageDetail(post.imageFull);
        showDialog("#image-detail");
    };

    self.post.comments.preload();
};

function GalleryImageViewModel(groupViewModel, item) {
    var self = this;
    self.group = groupViewModel;
    self.galleryImage = ko.observable(new GalleryImageItemModel(item, self));
    self.galleryImage().comments.preload();
};

function ActivityItemModel(item) {
    var self = this;
    self.id = item.id;
    self.activityType = item.activity_type;
    self.time = item.time;
    self.type = item.type;
    self.objectId = item.object_id;
    self.group = false;
    self.user = false;
    self.link = resolveLinkDetail(item);

    if (typeof(item.group) == "object" && item.group.hasOwnProperty("id")) {
        self.group = item.group;
        self.group.link = BASE_PATH + "group-" + self.group.id;
    }

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }

    var activityTypeLookup = {
        "user_follow": "followed",
        "user_unfollow": "unfollowed",
        "profile_created": "created profile",
        "profile_update": "updated profile",

        "vote": "voted on",
        "comment": "commented on",
        "comment_delete": "deleted comment on",

        "group_join": "joined",
        "group_leave": "left",
        "group_create": "created",
        "group_update": "updated",
        "group_delete": "deleted",
        "group_feed_post": "posted",
        "group_feed_delete": "deleted",

        "article_create": "created",
        "article_update": "updated",
        "article_delete": "deleted",

        "event_create": "created",
        "event_delete": "deleted",
        "event_update": "updated",
        "event_attend": "is attending",
        "event_miss": "will miss",

        "gallery_create": "created",
        "gallery_delete": "deleted",
        "gallery_update": "updated"
    };

    var typeLookup = {
        "comment": "comment",
        "post": "post",
        "gallery": "gallery",
        "gallery_image": "image",
        "group": "group",
        "article": "article",
        "event": "event",
        "user": "user",
        "vote": "vote"
    };

    self.activityText = activityTypeLookup[self.activityType];
    self.typeText = typeLookup[self.type];
};

function ActivityViewModel() {
    var self = this;
    PagingViewModel(self);
    self.entryObject = ActivityItemModel;
    self.method = "/activity/list";
    self.listType = ko.observable("everything");
    self.params = {"list_type": self.listType, "time_format": "ago"};
    self.cursorMode = true;
    self.loadMore();

    self.listType.subscribe(function(listType) {
        self.reload();
    });
};

function GroupItemModel(item) {
    var self = this;
    self.id = item.id;
    self.title = item.title;
    self.image = item.image;
    self.unreadCount = parseInt(item.unread_count);
    self.postsCount = parseInt(item.posts_visible_count);
    self.unread = self.unreadCount > 0;
    self.members = item.members;
    self.distance =  typeof(item.distance) != "undefined" ? Math.round(item.distance * 100) / 100 : undefined;
    self.link = BASE_PATH + "group-" + item.id;

    self.location = false;
    if (typeof(item.location) == "object") {
        self.location = {};
        self.location["longitude"] = item.location.longitude;
        self.location["latitude"] = item.location.latitude;
    }
};

function GroupsViewModel(load) {
    var self = this;
    PagingViewModel(self);
    self.type = ko.observable("group_members");
    self.entryObject = GroupItemModel;
    self.params = {"type": self.type};
    self.method = "/group/list";

    if (typeof(load) != "undefined" && load) {
        self.loadMore();
    }

    self.type.subscribe(function(type) {
        self.reload();
    });

    self.create = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/group/create",
            "params": {},
            "callback": function(response) {
                location.href = BASE_PATH + "group-" + response.group_id;
            }
        });
    };
};

function SearchViewModel() {
    var self = this;
    self.searchType = ko.observable("groups");
    self.groupsSearchViewModel = new GroupsSearchViewModel();
    self.usersSearchViewModel = new UsersSearchViewModel();

    self.groupsSearchViewModel.loadCountries();
    self.usersSearchViewModel.loadCountries();
};

function UsersSearchViewModel() {
    var self = this;
    PagingViewModel(self);
    LocationViewModel(self);
    self.entryObject = UserItemModel;
    self.method = "/user/search";

    self.name = ko.observable("");
    self.show = ko.observable(false);
    self.type = ko.observable("list");

    self.type.subscribe(function(value) {
        if (value == "map") {
            self.limit = 50;
            self.reload();
        }

        if (value == "list") {
            self.limit = 10;
            self.reload();
        }
    });

    self.search = function(form) {
        self.params = {
            "distance": self.distance(),
            "country_id": self.countryId(),
            "region_id": self.regionId(),
            "city_id": self.cityId(),
            "name": self.name()
        };

        self.show(true);
        self.reload();
    };
};

function GroupsSearchViewModel() {
    var self = this;
    PagingViewModel(self);
    LocationViewModel(self);
    self.entryObject = GroupItemModel;
    self.method = "/group/search";

    self.minMembers = ko.observable("");
    self.title = ko.observable("");
    self.tags = ko.observable("");
    self.show = ko.observable(false);
    self.type = ko.observable("list");

    self.type.subscribe(function(value) {
        if (value == "map") {
            self.limit = 50;
            self.reload();
        }

        if (value == "list") {
            self.limit = 10;
            self.reload();
        }
    });

    self.search = function(form) {
        self.params = {
            "min_members": self.minMembers(),
            "distance": self.distance(),
            "country_id": self.countryId(),
            "region_id": self.regionId(),
            "city_id": self.cityId(),
            "title": self.title(),
            "tags": self.tags()
        };

        self.show(true);
        self.reload();
    };

    self.create = function() {
        api("/group/create", {"title": self.title(), "description": "", "tags": self.tags()}, function(response) {
            location.href = BASE_PATH + "group-" + response.group_id;
        });
    };
};

function UserGroupsViewModel(userId) {
    var self = this;
    PagingViewModel(self);
    self.entryObject = GroupItemModel;
    self.params = {"user_id": userId};
    self.method = "/user/listMembership";
    self.loadMore();
};

function UserItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.name = item.name;
    self.image = item.image;
    self.link = BASE_PATH + "user-" + item.id;
    self.created = item.created;
    self.isFollowed = ko.observable(item.is_followed == "1");
    self.parent = parent;
    self.distance =  typeof(item.distance) != "undefined" ? Math.round(item.distance * 100) / 100 : undefined;
    self.location = false;
    if (typeof(item.location) == "object") {
        self.location = {};
        self.location["longitude"] = item.location.longitude;
        self.location["latitude"] = item.location.latitude;
    }
};

function FollowersViewModel(profileDetailModel) {
    var self = this;
    PagingViewModel(self);
    self.entryObject = UserItemModel;
    self.method = "/user/listFollowers";
    self.parent = profileDetailModel;
    self.loadMore();
};

function FollowingViewModel(profileDetailModel) {
    var self = this;
    PagingViewModel(self);
    self.entryObject = UserItemModel;
    self.method = "/user/listFollowing";
    self.parent = profileDetailModel;
    self.loadMore();
};

function ArticleItemModel(item, parent) {
    var self = this;
    self.parent = parent.group;
    self.id = item.id;
    self.title = item.title;
    self.contents = item.contents;
    self.created = item.created;
    self.time = item.time;
    self.visibility = item.visibility;
    self.canEdit = item.can_edit;
    self.canInteract = item.can_interact;

    self.voting = new VotingViewModel(self, "article", self.id, item.like_count, item.dislike_count);
    self.comments =  new CommentsViewModel(self, "article", self.id, item.comment_count);

    self.user = false;
    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }

    // fix contents - add target='_blank' to links
    if (typeof(self.contents) != "undefined") {
        // add target _blank to a href
        self.contents = self.contents.replace(/<a([^>]*)>/gi, function(p1, p2) {
            p2 = p2.replace(/target=(["'])\w*?(\1)/gi, ""); // remove existing target params
            return "<a " + p2 + " target='_blank'>";
        });

        // sandbox iframe
        self.contents = self.contents.replace(/<iframe([^>]*)>/gi, function(p1, p2) {
            // trust youtube -- this is unsafe when param is frameborder="src=//www.youtube.com/"
            //  if (p1.match(/src="?'?(https?:)?\/\/(www\.)?youtube\.com\//gi) !== null)
            //    return p1;
            p2 = p2.replace(/sandbox=(["'])\w*?(\1)/gi, ""); // remove existing target params
            return "<iframe " + p2 + " sandbox='allow-scripts allow-same-origin'>";
        });
    }
};

function ArticlesViewModel(groupViewModel) {
    var self = this;
    PagingViewModel(self);
    self.group = groupViewModel;

    self.params = {"group_id": self.group.id, "time_format": "day"};
    self.entryObject = ArticleItemModel;
    self.method = "/article/list";
    self.loadMore();
    self.posting = ko.observable(false);
    self.articleDetail = ko.observable();

    self.canCreate = ko.computed(function() {
        if (self.group.privacy().indexOf("admin_articles_only") != -1 && !self.group.canEdit())
            return false;
        return self.group.isMember();
    });

    self.group.isMember.subscribe(function(isMember) {
        self.reload();
    });

    self.create = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/article/create",
            "params": {"group_id": self.group.id},
            "callback": function(response) {
                self.reload();
                self.select(response.article_id);
            }
        });
    };

    self.update = function(form, articleId) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/article/update",
            "params": {"group_id": self.group.id, "article_id": articleId},
            "callback": function(response) {
                self.reload();
                self.select(articleId);
            }
        });
    };

    self.remove = function(articleId) {
        if (!confirm("Are you sure that you want to remove this article?"))
            return;

        api("/article/delete", {"article_id": articleId}, function(response) {
            self.articleDetail(undefined);
            self.reload();
        });
    };

    LoadDetailViewModel(self);
    self.detailMethod = "/article/detail";
    self.detailParamsCallback =  function(item) { return {"article_id": item, "time_format": "day"}; };
    self.detailModel = ArticleItemModel;
    self.detailUrlCallback = function(item) { return BASE_PATH + "group-" + self.group.id + "/article-" + item; };
    self.detailCallback = function(model) {
        self.articleDetail(model);
        model.comments.preload();
    };
};

function EventUserItemModel(item) {
    var self = this;
    self.user = false;

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }
};

function EventUsersViewModel(eventId, going, maybe, notGoing) {
    var self = this;
    self.eventId = eventId;

    self.goingCount = ko.observable(parseInt(going));
    self.maybeCount = ko.observable(parseInt(maybe));
    self.notGoingCount = ko.observable(parseInt(notGoing));

    PagingViewModel(self);
    self.attendanceType = ko.observable("going");
    self.entryObject = EventUserItemModel;
    self.method = "/event/listUsers";
    self.params = {"type": self.attendanceType, "event_id": self.eventId, "time_format": "day"};

    self.showNotGoing = function(targetModel) {
        targetModel(self);
        self.attendanceType("not_going");
        self.reload();
        showDialog("#event-users-detail");
    };

    self.showGoing = function(targetModel) {
        targetModel(self);
        self.attendanceType("going");
        self.reload();
        showDialog("#event-users-detail");
    };

    self.showMaybe = function(targetModel) {
        targetModel(self);
        self.attendanceType("maybe");
        self.reload();
        showDialog("#event-users-detail");
    };
};

function EventItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.title = item.title;
    self.message = item.message;
    self.created = item.created;
    self.time = item.time;
    self.timeEnd = item.time_end;
    self.attending = ko.observable(item.attending);
    self.visibility = item.visibility;
    self.canEdit = item.can_edit;
    self.canInteract = item.can_interact;

    self.users = new EventUsersViewModel(self.id,  item.going, item.maybe, item.not_going);
    self.voting = new VotingViewModel(self, "event", self.id, item.like_count, item.dislike_count);
    self.comments =  new CommentsViewModel(self, "event", self.id, item.comment_count);

    self.user = false;
    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }

    self.location = false;
    if (typeof(item.location) == "object") {
        self.location = {};
        self.location["title"] = item.location.title;
        self.location["longitude"] = item.location.longitude;
        self.location["latitude"] = item.location.latitude;
    }
};

function EventsViewModel(groupViewModel) {
    var self = this;
    PagingViewModel(self);
    self.group = groupViewModel;

    self.posting = ko.observable(false);
    self.method = "/event/list";
    self.params = {"group_id": self.group.id, "time_format": "day"};
    self.entryObject = EventItemModel;
    self.loadMore();

    self.eventDetail = ko.observable();
    self.eventUsersDetail = ko.observable();

    self.canCreate = ko.computed(function() {
        if (self.group.privacy().indexOf("admin_events_only") != -1 && !self.group.canEdit())
            return false;
        return self.group.isMember();
    });

    self.group.isMember.subscribe(function(isMember) {
        self.reload();
    });

    self.create = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/event/create",
            "params": {"group_id": self.group.id},
            "callback": function(response) {
                self.reload();
                self.select(response.event_id);
            }
        });
    };

    self.update = function(form, event) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/event/update",
            "params": {"group_id": self.group.id, "event_id": event.id},
            "callback": function(response) {
                self.load(event.id);
            }
        });
    };

    self.remove = function(event) {
        if (!confirm("Are you sure that you want to remove this event?"))
            return;

        api("/event/delete", {"event_id": event.id}, function(response) {
            self.eventDetail(undefined);
            self.reload();
        }, self.posting);
    };

    self.going = function(event) {
        api("/event/attend", {"event_id": event.id, "type": "going"}, function(response) {
            event.attending("going");
            event.users.goingCount(event.users.goingCount() + 1);
        }, self.posting);
    };

    self.maybe = function(event) {
        api("/event/attend", {"event_id": event.id, "type": "maybe"}, function(response) {
            event.attending("maybe");
            event.users.maybeCount(event.users.maybeCount() + 1);
        }, self.posting);
    };

    self.notGoing = function(event) {
        api("/event/attend", {"event_id": event.id, "type": "not_going"}, function(response) {
            event.attending("not_going");
            event.users.notGoingCount(event.users.notGoingCount() + 1);
        }, self.posting);
    };

    self.miss = function(event) {
        api("/event/miss", {"event_id": event.id}, function(response) {
            if (event.attending() == "not_going")
                event.users.notGoingCount(event.users.notGoingCount() - 1)
            else if (event.attending() == "maybe")
                event.users.maybeCount(event.users.maybeCount() - 1)
            else if (event.attending() == "going")
                event.users.goingCount(event.users.goingCount() - 1)

            event.attending(null);
        }, self.posting);
    };

    LoadDetailViewModel(self);
    self.detailMethod = "/event/detail";
    self.detailParamsCallback =  function(item) { return {"event_id": item, "time_format": "day"}; };
    self.detailModel = EventItemModel;
    self.detailUrlCallback = function(item) { return BASE_PATH + "group-" + self.group.id + "/event-" + item; };
    self.detailCallback = function(model) {
        self.eventDetail(model);
        model.comments.preload();
    };
};

function AttendingEventItemModel(item) {
    var self = this;
    self.id = item.id;
    self.title = item.title;
    self.message = item.message;
    self.created = item.created;
    self.time = item.time;
    self.timeEnd = item.time_end;
    self.canEdit = item.can_edit;
    self.groupId = item.group_id;
    self.attending = item.attending;
    self.users = new EventUsersViewModel(self.id,  item.going, item.maybe, item.not_going);
    self.link =  BASE_PATH + "group-" + self.groupId + "/event-" + self.id;
};

function AttendingEventsViewModel() {
    var self = this;
    PagingViewModel(self);
    self.params = {"image_format": "p50x50", "time_format": "day"};
    self.entryObject = AttendingEventItemModel;
    self.method = "/event/listAttending";
    self.loadMore();
};

function GalleryImageDetailItemModel(galleryItem) {
    var self = this;
    self.galleryItem = galleryItem;
    self.image = ko.observable();
    self.currentIndex = 0;

    self.setImage = function(index) {
        self.currentIndex = index;
        var images = self.galleryItem.galleryImages().entries();
        var galleryImage = images[self.currentIndex];
        self.image(galleryImage);

        if (!galleryImage.comments.show())
            galleryImage.comments.preload();
    };

    self.previousImage = function() {
        if (self.currentIndex < 1) return;
        self.setImage(self.currentIndex - 1);
    };

    self.nextImage = function() {
        if (self.currentIndex + 1 >= self.galleryItem.galleryImages().entries().length) {
            // load next page if we are on end
            if (self.galleryItem.galleryImages().hasMore() && !self.galleryItem.galleryImages().loading()) {
                self.galleryItem.galleryImages().loadMore(function() {
                    self.nextImage();
                });
            }

            return;
        }
        self.setImage(self.currentIndex + 1);
    };
};

function GalleryImageItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.message = item.message;
    self.image = item.image;
    self.imageFull = item.image_full;
    self.time = item.time;
    self.canEdit = parent.canEdit;
    self.canInteract = item.can_interact;

    if (typeof(parent.group) == "object") {
        self.galleryLink = BASE_PATH + "group-" + parent.group.id + "/" + "gallery-" + item.gallery_id;
        self.link = BASE_PATH + "group-" + parent.group.id + "/" + "gallery-image-" + self.id;
    }

    self.voting = new VotingViewModel(self, "gallery_image", self.id, item.like_count, item.dislike_count);
    self.comments =  new CommentsViewModel(self, "gallery_image", self.id, item.comment_count);
};

function GalleryImagesViewModel(galleryItemModel) {
    var self = this;
    PagingViewModel(self);
    self.group = galleryItemModel.group;
    self.cursorMode = true;
    self.canEdit = galleryItemModel.canEdit;
    self.params = {"gallery_id": galleryItemModel.id, "image_format": "p300x300", "time_format": "ago"};
    self.entryObject = GalleryImageItemModel;
    self.method = "/gallery/listImages";
    self.posting = ko.observable();
    self.loadMore();

    self.remove = function(galleryImage) {
        if (!confirm("Are you sure that you want to remove this image?"))
            return;

        api("/gallery/deleteImage", {"image_id": galleryImage.id}, function(response) {
            self.entries.remove(galleryImage);
            galleryItemModel.imageCount(galleryItemModel.imageCount() - 1);
        });
    };

    self.show = function(galleryImage) {
        var imageDetail = galleryItemModel.galleries.imageDetail;
        var index = self.entries.indexOf(galleryImage);
        imageDetail(new GalleryImageDetailItemModel(galleryItemModel));
        imageDetail().setImage(index);
        showDialog("#image-detail");
    };
};

function GalleryItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.group = parent.group;
    self.title = item.title;
    self.created = item.created;
    self.time = item.time;
    self.visibility = item.visibility;
    self.image = item.image;
    self.imageCount = ko.observable(parseInt(item.image_count));
    self.galleryImages = ko.observable();
    self.user = false;
    self.galleries = parent;
    self.canEdit = item.can_edit;
    self.canInteract = item.can_interact;

    self.voting = new VotingViewModel(self, "gallery", self.id, item.like_count, item.dislike_count);
    self.comments =  new CommentsViewModel(self, "gallery", self.id, item.comment_count);

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }
};

function GalleriesViewModel(groupViewModel) {
    var self = this;
    PagingViewModel(self);
    self.group = groupViewModel;

    self.params = {"group_id": self.group.id, "image_format": "p50x50", "time_format": "day"};
    self.entryObject = GalleryItemModel;
    self.method = "/gallery/list";
    self.loadMore();

    self.posting = ko.observable(false);
    self.galleryDetail = ko.observable();
    self.imageDetail = ko.observable();

    self.canCreate = ko.computed(function() {
        if (self.group.privacy().indexOf("admin_galleries_only") != -1 && !self.group.canEdit())
            return false;
        return self.group.isMember();
    });

    self.group.isMember.subscribe(function(isMember) {
        self.reload();
    });

    self.create = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/gallery/create",
            "params": {"group_id": self.group.id},
            "callback": function(response) {
                self.reload();
            }
        });
    };

    self.update = function(form, gallery) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/gallery/update",
            "params": {"group_id": self.group.id, "gallery_id": gallery.id},
            "callback": function(response) {
                self.load(gallery.id);
            }
        });
    };

    self.addImage = function(form, gallery) {
        submitForm({
            "form": form,
            "method": "/gallery/addImage",
            "params": {"gallery_id": gallery.id},
            "koLoader": self.posting,
            "callback": function(response) {
                self.load(gallery.id);
            }
        });
    };

    self.remove = function(gallery) {
        if (!confirm("Are you sure that you want to remove this gallery?"))
            return;

        api("/gallery/delete", {"gallery_id": gallery.id}, function(response) {
            self.galleryDetail(undefined);
            self.reload();
        }, self.posting);
    };

    LoadDetailViewModel(self);
    self.detailMethod = "/gallery/detail";
    self.detailParamsCallback =  function(item) { return {"gallery_id": item, "time_format": "day"}; };
    self.detailModel = GalleryItemModel;
    self.detailUrlCallback = function(item) { return BASE_PATH + "group-" + self.group.id + "/gallery-" + item; };
    self.detailCallback = function(model) {
        self.galleryDetail(model);
        model.comments.preload();
        model.galleryImages(new GalleryImagesViewModel(model));
    };
};

function GroupRequestItemModel(item) {
    var self = this;
    self.id = item.id;
    self.created = item.created;
    self.type = item.type;

    self.userFrom = item.user_from;
    self.userFrom.link = BASE_PATH + "user-" + self.userFrom.id;

    self.userTo = false;
    if (typeof(item.user_to) == "object" && item.user_to.hasOwnProperty("id")) {
        self.userTo = item.user_to;
        self.userTo.link = BASE_PATH + "user-" + self.userTo.id;
    }

    var typeLookup = {
        "request_admin": "admin request",
        "request_member": "member request",
        "request_join": "want to join"
    };

    self.typeText = typeLookup[self.type];
};

function GroupRequestsViewModel(groupId) {
    var self = this;
    PagingViewModel(self);

    self.params = {"group_id": groupId, "image_format": "p50x50", "time_format": "day"};
    self.entryObject = GroupRequestItemModel;
    self.method = "/group/listGroupRequests";

    self.cancel = function(request) {
        api("/group/cancelRequest", {"request_id": request.id}, function() {
            self.entries.remove(request);
        });
    };

    self.accept = function(request) {
        api("/group/acceptRequest", {"request_id": request.id}, function() {
            self.entries.remove(request);
        });
    };
};

function GroupUsersViewModel(groupId) {
    var self = this;
    PagingViewModel(self);

    self.type = ko.observable("members");
    self.listType = ko.observable("group_members");

    self.params = {"group_id": groupId, "type": self.listType, "image_format": "p50x50", "time_format": "day"};
    self.entryObject = UserItemModel;
    self.method = "/group/listUsers";

    self.type.subscribe(function(type) {
        self.listType(type == "admins" ? "group_admins" : "group_members");
    });

    self.remove = function(user) {
        var method = (self.type() == "admins") ? "/group/removeAdmin" : "/group/removeMember";
        api(method, {"group_id": groupId, "user_id": user.id}, function() {
            self.entries.remove(user);
        });
    };

    self.add = function() {
        var type = (self.type() == "admins") ? "request_admin" : "request_member";
        selectUser(function(user) {
            api("/group/createRequest", {"group_id": groupId, "user_id": user.id, "type": type}, function() {
                showMessage("Request send", "User have to confirm your request now");
            });
        });
    };
};

function GroupViewModel(group) {
    var self = this;
    self.posting = ko.observable(false);
    self.id = group.id;
    self.title = group.title;
    self.created = group.created;
    self.members = ko.observable(parseInt(group.members));
    self.isMember = ko.observable(group.is_member == true);
    self.canEdit = ko.observable(group.can_edit);
    self.users = new GroupUsersViewModel(group.id);
    self.requests = new GroupRequestsViewModel(group.id);
    self.privacy = ko.observable(group.privacy);
    self.canInteract = group.can_interact;

    self.approvalNeeded = ko.computed(function() {
        if (self.privacy().indexOf("approval_needed") != -1)
            return true;
        return false;
    });

    self.canJoin = ko.computed(function() {
        if (self.approvalNeeded())
            return false;
        return self.canInteract;
    });

    self.update = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/group/update",
            "params": {"group_id": self.id},
            "callback": function(response) {
            }
        });
    };

    self.remove = function() {
        if (!confirm("Are you sure that you want to remove group " + self.title + "?"))
            return;

        api("/group/delete", {"group_id": self.id}, function(response) {
        });
    };

    self.join = function() {
        api("/group/join", {"group_id": self.id}, function(response) {
            self.members(self.members() + 1);
            self.isMember(true);
        });
    };

    self.requestJoin = function() {
        api("/group/createRequest", {"group_id": self.id, "type": "request_join"}, function() {
            showMessage("Request send", "Group admin have to confirm your request now");
        });
    };

    self.leave = function() {
        if (!confirm("Are you sure that you want to leave this group?"))
            return;

        api("/group/leave", {"group_id": self.id}, function(response) {
            self.members(self.members() - 1)
            self.isMember(false);
        });
    };

    self.setImage = function(form) {
        submitForm({
            "form": form,
            "method": "/group/setImage",
            "params": { "group_id": self.id },
            "koLoader": self.posting,
            "callback": function(response) {
            }
        });
    };

    self.updateLocation = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/group/updateLocation",
            "params": {"group_id": self.id},
            "callback": function(response) {
            }
        });
    };

    self.removeImage = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/group/removeImage",
            "params": {"group_id": self.id},
            "callback": function(response) {
            }
        });
    };

    self.report = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/group/report",
            "params": {"group_id": self.id},
            "callback": function(response) {
            }
        });
    };

    self.invite = function() {
        self.users.type("members");
        self.users.add();
    };

    self.showMembers = function() {
        self.users.type("members");
        self.users.reload();
        showDialog("#group-users-list");
    };

    self.showAdmins = function() {
        self.users.type("admins");
        self.users.reload();
        showDialog("#group-users-list");
    };

    self.showRequests = function() {
        self.requests.reload();
        showDialog("#group-requests");
    };
};

function UserActivityViewModel(userId) {
    var self = this;
    PagingViewModel(self);
    self.userDetailModel = parent;
    self.entryObject = ActivityItemModel;
    self.method = "/activity/list";
    self.params = {"list_type": "user", "user_id": userId, "time_format": "ago"};
    self.cursorMode = true;
    self.loadMore();
};

function UserDetailModel(user) {
    var self = this;
    self.id = user.id;
    self.name = user.name;
    self.followers = ko.observable(parseInt(user.followers));
    self.following = ko.observable(parseInt(user.following));
    self.isFollowing = ko.observable(user.is_following == "1");
    self.isFollowed = ko.observable(user.is_followed == "1");
    self.posting = ko.observable(false);
    self.page = ko.observable("detail");
    self.groups = ko.observable();
    self.messages = ko.observable();
    self.activity = ko.observable();

    self.page.subscribe(function(type) {
        setHashtagPage(type);

        if (type == "activity") {
            self.activity(new UserActivityViewModel(self.id));
        }

        if (type == "groups") {
            self.groups(new UserGroupsViewModel(self.id));
        }

        if (type == "messages") {
            //self.messages(new MessagesViewModel(self.id));
        }
    });

    var loadPage = getHashtagPage();
    if (loadPage)
        self.page(loadPage);

    self.follow = function() {
        api("/user/follow", {"user_id": self.id}, function(response) {
            self.isFollowed(true);
            self.followers(self.followers() + 1);
        });
    };

    self.unfollow = function() {
        api("/user/unfollow", {"user_id": self.id}, function(response) {
            self.isFollowed(false);
            self.followers(self.followers() - 1);
        });
    };

    self.report = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/user/report",
            "params": {"user_id": self.id},
            "callback": function(response) {
            }
        });
    };

    self.createConversation = function() {
        api("/message/createConversation", {"user_id": self.id}, function(response) {
            location.href = BASE_PATH + "messages";
        });
    };
};

function UserRequestItemModel(item) {
    var self = this;
    self.id = item.id;
    self.created = item.created;
    self.type = item.type;

    self.user = item.user;
    self.user.link = BASE_PATH + "user-" + self.user.id;

    self.group = item.group;
    self.group.link = BASE_PATH + "group-" + self.group.id;
};

function UserRequestsViewModel(profileDetailModel) {
    var self = this;
    PagingViewModel(self);

    self.params = {"image_format": "p50x50", "time_format": "day"};
    self.entryObject = UserRequestItemModel;
    self.method = "/group/listUserRequests";
    self.loadMore();

    self.cancel = function(request) {
        api("/group/cancelRequest", {"request_id": request.id}, function() {
            self.entries.remove(request);
        });
    };

    self.accept = function(request) {
        api("/group/acceptRequest", {"request_id": request.id}, function() {
            self.entries.remove(request);
        });
    };
};

function ProfileDetailModel(user) {
    var self = this;
    self.birthdayDay = ko.observable(user.birthday.day);
    self.birthdayMonth = ko.observable(user.birthday.month);
    self.birthdayYear = ko.observable(user.birthday.year);
    self.birthday = ko.computed(function() { return self.birthdayDay() + "." + self.birthdayMonth() + "." + self.birthdayYear(); });

    self.page = ko.observable("detail");
    self.following = ko.observable();
    self.followers = ko.observable();

    self.followingCount = ko.observable(parseInt(user.following));
    self.followersCount = ko.observable(parseInt(user.followers));

    self.requests = ko.observable();
    self.posting = ko.observable(false);

    self.page.subscribe(function(type) {
        setHashtagPage(type);

        if (type == "following") {
            self.following(new FollowingViewModel(self));
        }

        if (type == "followers") {
            self.followers(new FollowersViewModel(self));
        }

        if (type == "requests") {
            self.requests(new UserRequestsViewModel(self));
        }
    });

    var loadPage = getHashtagPage();
    if (loadPage)
        self.page(loadPage);

    self.updateLocation = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/user/updateLocation",
            "params": {},
            "callback": function(response) {
            }
        });
    };

    self.changePassword = function(form) {
        var array = serializeForm(form);
        if (array["password"] != array["password_again"]) {
            showMessage("Error", "Passwords do not match");
            return;
        }

        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/user/changePassword",
            "params": {},
            "callback": function(response) {
            }
        });
    };

    self.setImage = function(form) {
        submitForm({
            "form": form,
            "method": "/user/setImage",
            "koLoader": self.posting,
            "callback": function(response) {
            }
        });
    };

    self.removeImage = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/user/removeImage",
            "params": {},
            "callback": function(response) {
            }
        });
    };

    self.update = function(form) {
        submitForm({
            "form": form,
            "reset": false,
            "koLoader": self.posting,
            "method": "/user/update",
            "params": {},
            "callback": function(response) {
            }
        });
    };

    self.follow = function(userItem) {
        api("/user/follow", {"user_id": userItem.id}, function(response) {
            userItem.isFollowed(true);
            self.followingCount(self.followingCount() + 1);
        });
    };
};

function NotificationItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.time = item.time;
    self.read = (item.status == "read");
    self.notificationType = item.notification_type;
    self.fromUserCount = parseInt(item.from_user_count);
    self.otherUsersCount = self.fromUserCount - 1;

    self.objectId = item.object_id;
    self.type = item.type;

    self.group = false;
    self.user = false;
    self.link = resolveLinkDetail(item);

    if (typeof(item.group) == "object" && item.group.hasOwnProperty("id")) {
        self.group = item.group;
        self.group.link = BASE_PATH + "group-" + self.group.id;
    }

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }

    var notificationTypeLookup = {
        "join_request": "want you to join group",
        "vote": "voted on your",
        "comment": "commented on your",
        "follow": (self.otherUsersCount > 0 ? "are " : "is ") + "now following you",
        "event_attending": "attending",
        "made_member": "made you member of group"
    };

    var typeLookup = {
        "comment": "comment",
        "post": "post",
        "gallery": "gallery",
        "gallery_image": "image",
        "group": "group",
        "article": "article",
        "event": "event",
        "user": "user",
        "vote": "vote"
    };

    // fix this type
    if (self.notificationType == "follow")
        self.type = "";

    self.notificationText = notificationTypeLookup[self.notificationType];
    self.typeText = typeLookup[self.type];
};

function NotificationsViewModel() {
    var self = this;
    PagingViewModel(self);
    self.params = {"mark_as_read": "1", "time_format": "ago"};
    self.entryObject = NotificationItemModel;
    self.method = "/notification/list";
    self.loadMore();
};

function MessageItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.message = item.message;
    self.created = item.created;

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id;
    }

    self.seen = ko.observableArray();
};

function MessagesViewModel(conversationId, parent) {
    var self = this;
    PagingViewModel(self);

    self.conversationId = ko.observable(conversationId);
    self.params = {"mark_as_read": "1", "time_format": "ago", "conversation_id": self.conversationId, "image_format": "p50x50"};
    self.entryObject = MessageItemModel;
    self.method = "/message/list";
    self.cursorMode = true;
    self.prepend = true;
    self.members = ko.observableArray();

    self.reload();

    // get conversation detail
    api("/message/detailConversation", {"conversation_id": self.conversationId, "time_format": "ago", "image_format": "p50x50"},
        function(response) {
            self.updateMembers(response.members);
        }
    );

    self.updateMembers = function(members) {
        self.members.removeAll();
        for (var i = 0; i < members.length; i++) {
            var member = members[i];
            member.link = BASE_PATH + "user-" + member.id;
            self.members.push(member);
        }
    };

    self.resolveUserDetail = function(userId, callback) {
        // cache users so we are fast
        if (!self.cacheUsers)
            self.cacheUsers = {};

        if (self.cacheUsers[userId]) {
            callback(self.cacheUsers[userId]);
            return;
        }

        api("/user/detail", {"user_id": userId, "image_format": "p50x50"}, function(response) {
            var user = {"id": response.id, "name": response.name, "image": response.image};
            self.cacheUsers[userId] = user;
            callback(user);
        });
    };

    self.appendMessage = function(fromUserId, messageId, message) {
        self.resolveUserDetail(fromUserId, function(fromUser) {
            var entry = {"id" : messageId, "message": message, "created": "now", "user": fromUser};
            var object = new self.entryObject(entry, self);

            self.entries.push(object);
            window.scrollTo(0,document.body.scrollHeight);

            // stop any previous mark as read timeouts
            if (self.markAsReadTimeout)
                window.clearTimeout(self.markAsReadTimeout);

            // wait 5 sec and mark as read
            self.markAsReadTimeout = window.setTimeout(function() {
                api("/message/markAsRead", {"conversation_id": self.conversationId}, function(result) { });
            }, 5000);
        });
    };

    self.sendMessage = function(form) {
        submitForm({
            "form": form,
            "koLoader": self.posting,
            "method": "/message/create",
            "params": {"conversation_id": self.conversationId()},
            "callback": function(response) {
            }
        });
    };

    self.extendConversation = function() {
        selectUser(function(user) {
            api("/message/extendConversation", {"conversation_id": self.conversationId, "user_id": user.id}, function() {
            });
        });
    };

    self.leaveConversation = function() {
        api("/message/leaveConversation", {"conversation_id": self.conversationId}, function() {
        });
    };

    self.checkSeenUser = function(userId, messageId) {
        // small cache to remove user previously added to seen list
        if (!self.cacheSeen)
            self.cacheSeen = {};
        if (self.cacheSeen[userId] && self.cacheSeen[userId].list) {
            self.cacheSeen[userId].list.remove(self.cacheSeen[userId].item);
            self.cacheSeen[userId] = undefined;
        }

        // find message that was seen
        for (var i = 0; i < self.entries().length; i++) {
            if (self.entries()[i].id == messageId) {
                var seenList = self.entries()[i].seen;
                // add user to seen list for this message
                self.resolveUserDetail(userId, function(user) {
                    seenList.push(user);
                    // remember where we added it so we can remove it later
                    self.cacheSeen[userId] = {"list": seenList, "item": user};
                });
            }
        }
    };
};

function ConversationItemModel(item, parent) {
    var self = this;
    self.id = item.id;
    self.message = ko.observable(item.message);
    self.read = ko.observable(item.read);
    self.updated = ko.observable(item.updated);
    self.memberCount = ko.observable(item.member_count);

    if (typeof(item.user) == "object" && item.user.hasOwnProperty("id")) {
        self.user = item.user;
        self.user.link = BASE_PATH + "user-" + self.user.id + "#page-messages";
    }
};

function ConversationsViewModel() {
    var self = this;
    PagingViewModel(self);
    self.params = {"time_format": "ago", "image_format": "p50x50"};
    self.entryObject = ConversationItemModel;
    self.method = "/message/listConversations";
    self.loadMore();

    self.messages = ko.observable();

    self.showMessages = function(item) {
        item.read(true);
        self.messages(new MessagesViewModel(item.id, self));
    };

    // simple check over api request
    self.checkLatestMessages = function(callback) {
        // wait until loaded
        if (self.loading()) {
            if (typeof(callback) != "undefined")
                callback();
            return;
        }

        // no conversation selected
        if (self.messages() == undefined)
            return;

        var messages = self.messages();

        // receive new messages
        var params = {
            "time_format": "ago",
            "image_format": "p50x50",
            "mark_as_read": "0",
            "limit": "100",
            "conversation_id": messages.conversationId()
        };

        var lastId = 0;
        if (messages.entries().length > 0)
            lastId = messages.entries()[messages.entries().length - 1].id;

        params["since_id"] = lastId;

        api("/message/list", params, function(response) {
            var entries = response.data;
            // we are going to prepend so reverse returned array
            entries.reverse();

            for (var i = 0; i < entries.length; i++)
                messages.appendMessage(entries[i].user.id, entries[i].id, entries[i].message);

            if (typeof(callback) != "undefined")
                callback();
        });
    };

    self.conversationGetCurrentId = function() {
        var conversationId = null;
        if (self.messages() != undefined)
            conversationId = self.messages().conversationId();
        return conversationId;
    };

    self.conversationFindEntry = function(conversationId) {
        // find existing
        for (var i = 0; i < self.entries().length; i++) {
            if (self.entries()[i].id == conversationId) {
                var entry = self.entries()[i];
                return entry;
            }
        }
        return null;
    };

    self.conversationBringToTop = function(conversationId) {
        var entry = self.conversationFindEntry(conversationId);
        if (entry != null)
            self.entries.splice(0, 0, self.entries.splice(self.entries.indexOf(entry), 1)[0]);
    };

    self.conversationFocus = function(conversationId, message, read) {
        var entry = self.conversationFindEntry(conversationId);
        if (entry != null) {
            // update conversation model details
            if (typeof(message) == "undefined" || typeof(read) == "undefined") {
                api("/message/detailConversation", {"conversation_id": conversationId, "time_format": "ago", "image_format": "p50x50"}, function(response) {
                    entry.read(response.read);
                    entry.message(response.message);
                    entry.updated(response.updated);
                    entry.memberCount(response.member_count);

                    // update members if we are under conversation
                    if (conversationId == self.conversationGetCurrentId())
                        self.messages().updateMembers(response.members);
                });
            }
            else {
                // always set as read if currently opened
                entry.read(conversationId == self.conversationGetCurrentId() ? true : read);
                entry.message(message);
                entry.updated("now");
            }

            self.conversationBringToTop(conversationId);
            return;
        }

        // load a new one and insert it to top
        api("/message/detailConversation", {"conversation_id": conversationId, "time_format": "ago", "image_format": "p50x50"}, function(response) {
            var conversation = new ConversationItemModel(response, self);
            self.entries.unshift(conversation);

            // fix offset
            self.offset += 1;
        });
    };

    self.conversationRemove = function(conversationId) {
        if (self.conversationGetCurrentId() == conversationId)
            self.messages(undefined);

        // remove from conversations
        var entry = self.conversationFindEntry(conversationId);
        if (entry != null) {
            self.entries.remove(entry);
            self.offset -= 1;
        }
    }

    self.receiveWebSockets = function() {
        var ws = new WebSocket("ws://" + window.location.host + ":8080");
        ws.onopen = function() {
            console.log("sockets open session init");

            api("/user/getSubscriptionSession", {}, function(response) {
                ws.send(JSON.stringify({"method": "login", "session": response.subscription_session }));
            });
        };

        ws.onmessage = function (event) {
            var data = JSON.parse(event.data);
            console.log("received");
            console.log(data);

            // check for new messages in this conversation
            if (data.action == "newMessage") {
                // either append message or focus conversation
                if (data.id == self.conversationGetCurrentId())
                    self.messages().appendMessage(data.params["user_id"], data.params["message_id"], data.params["message"]);

                self.conversationFocus(data.id, data.params["message"], false);
            }

            // somebody joined conversation
            if (data.action == "join") {
                self.conversationFocus(data.id);
            }

            // somebody left conversation
            if (data.action == "leave") {
                if (data.params.user_ids.indexOf(user.id.toString()) != -1) {
                    // current user, remove conversation from list
                    self.conversationRemove(data.id);
                }
                else {
                    // focus update
                    self.conversationFocus(data.id);
                }
            }

            // check against conversation id
            if (data.action == "seen" && data.id == self.conversationGetCurrentId())
                self.messages().checkSeenUser(data.params.user_id, data.params.message_id);
        };

        ws.onclose = function() {
            console.log("closed");
            // sockets failed - fallback to simple receive
            self.receive();
        };
    };

    self.receive = function() {
        // check for new messages using rest api
        if (!window.messageCheck) {
            window.messageCheck = function() {
                // TODO check for conversation list updates
                self.checkLatestMessages(function() {
                    // check every 5 second for new message
                    setTimeout(window.messageCheck, 5000);
                });
            };
            window.messageCheck();
        }
    };

    if ("WebSocket" in window && false) {
        self.receiveWebSockets();
    }
    else {
        self.receive();
    }
};

function LoggedUserDetailModel() {
    var self = this;
    self.unreadMessages = ko.observable((typeof(Storage) !== "undefined" && sessionStorage.unreadMessages) ? sessionStorage.unreadMessages : 0);
    self.unreadNotifications = ko.observable((typeof(Storage) !== "undefined" && sessionStorage.unreadNotifications) ? sessionStorage.unreadNotifications : 0);

    // get unread count
    api("/user/getUnreadCount", {}, function(response) {
        self.unreadMessages(parseInt(response.unread_conversations));
        self.unreadNotifications(parseInt(response.unread_notifications));

        if (typeof(Storage) !== "undefined") {
            sessionStorage.unreadMessages = self.unreadMessages();
            sessionStorage.unreadNotifications = self.unreadNotifications();
        }
    });
};