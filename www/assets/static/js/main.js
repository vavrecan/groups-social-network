var showMessage = function(title, message, callback, data, form) {
    $("#message h3").text(title);
    $("#message p").text(message);

    $.colorbox({inline:true, href: '#message', onClosed: function(e) {
        if (typeof(callback) != "undefined")
            callback(data, form);
    }});
};

var showDialog = function(element) {
    $.colorbox( { inline:true, href: element } );
};

$.fn.tokenize = function() {
    if (this.length > 1) {
        // separate if multiple
        this.each(function() { $(this).tokenize(); });
        return;
    }

    if (this.length == 0) {
        // nothing to do here
        return;
    }

    var controlElement = this;
    var saveElement = null;

    var save = function() {
        var data = {};
        controlElement.find(".token").each(function() {
            data[$(this).data("token-id")] = $(this).data("token-name");
        });
        saveElement.val(JSON.stringify(data));
        saveElement.trigger("change"); // so that knockout knows value is updated
    };

    var findTokenById = function(id) {
        var element = null;
        controlElement.find(".token").each(function() {
            if ($(this).data("token-id") == id) {
                element = $(this);
                return false;
            }
        });
        return element;
    };

    var addToken = function(id, name) {
        if (name == "")
            return;

        if (id == null || id == "")
            id = name;

        var previousElement = findTokenById(id);
        if (previousElement != null)
            return;

        var token = $("<div>");
        token.addClass("token");
        token.data("token-id", id);
        token.data("token-name", name);
        token.text(name);

        var tokenRemove = $("<div>");
        tokenRemove.addClass("token-remove");
        token.append(tokenRemove);

        if (controlElement.find(".token").length > 0)
            controlElement.find(".token:last").after(token);
        else
            controlElement.prepend(token);

        save();
    };

    var removeToken = function(id) {
        var element = findTokenById(id);
        if (element == null)
            return;
        element.remove();
        save();
    };

    var initialize = function() {
        // handle enter key
        controlElement.find("input[type='text']").bind("keypress", {}, function(e) {
            var code = (e.keyCode ? e.keyCode : e.which);
            if (code == 13) {
                e.preventDefault();

                var name = $(this).val();
                $(this).val("");
                addToken(null, name);
            }
        });

        // backspace
        controlElement.find("input[type='text']").bind("keydown", {}, function(e) {
            var code = (e.keyCode ? e.keyCode : e.which);
            if (code == 8) {
                // remove last token
                if (typeof($(this)[0].selectionStart) == "number" && $(this)[0].selectionStart == 0) {
                    var last = controlElement.find(".token:last");
                    if (last.length > 0)
                        removeToken(last.data("token-id"));
                }
            }
        });

        // left the area
        controlElement.find("input[type='text']").blur(function() {
            var name = $(this).val();
            $(this).val("");
            addToken(null, name);
        });

        // add from existing tokens
        saveElement = controlElement.find("input[type='hidden']");
        if (saveElement.length > 0) {
            var sourceData = saveElement.val();
            if (sourceData != "") {
                var tokens = JSON.parse(sourceData);
                for (var i in tokens)
                    addToken(i, tokens[i]);
            }
        }

        // when removing tag just remove it
        controlElement.on("click", ".token-remove", function() {
            removeToken($(this).parent().data("token-id"));
        });
    };

    initialize();
};

$.fn.ajaxify = function(callback) {
    var elements = this;

    var startFormLoading = function(form) {
        var buttons = form.find(".button, .sign-button");
        buttons.attr("disabled", "disabled");
        buttons.addClass("wait");
    };

    var endFormLoading = function(form) {
        var buttons = form.find(".button, .sign-button");
        buttons.removeAttr("disabled");
        buttons.removeClass("wait");
    };

    var executeResponse = function(callback, data, form) {
        if (typeof(data.redirect) != "undefined") {
            location.href = data.redirect;
        }
        else if (typeof(data.message) != "undefined") {
            var message = data.message;
            var title = typeof(data.title) == "undefined" ? "" : data.title;
            showMessage(title, message, callback, data, form);
        }
        else if (typeof(data.error) != "undefined") {
            showMessage("Error", data.error.message, callback, data, form);
        }
        else {
            //$.colorbox.close();
            if (typeof(callback) != "undefined")
                callback(data, form);
        }
    };

    elements.submit(function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var form = $(this);
        startFormLoading(form);

        $.post(
            form.attr("action"),
            "format=json&" + form.serialize(),
            function(data) {
                endFormLoading(form);
                executeResponse(callback, data, form);
            },
            "json"
        ).fail(function(jqXHR, textStatus) {
            endFormLoading(form);
            showMessage("Connection Error", textStatus);
        });
    });

    return this;
};

$(document).ready(function() {
    $(".open-dialog").click(function(e) {
        e.preventDefault();
        $.colorbox( { inline:true, href: $(this).attr("href") } );
    });

    $(".ajax-form").ajaxify();
    $(".tokenizer").tokenize();
});