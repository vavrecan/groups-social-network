if (!Array.prototype.indexOf)
{
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        var len = this.length >>> 0;

        var from = Number(arguments[1]) || 0;
        from = (from < 0)
            ? Math.ceil(from)
            : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}

var _debug = false;
var _placeholderSupport = function() {
    var t = document.createElement("input");
    t.type = "text";
    return (typeof t.placeholder !== "undefined");
}();

window.onload = function() {
    var arrInputs = document.getElementsByTagName("input");
    for (var i = 0; i < arrInputs.length; i++) {
        var curInput = arrInputs[i];
        if (!curInput.type || curInput.type == "" || curInput.type == "text")
            HandlePlaceholder(curInput);
        else if (curInput.type == "password")
            ReplaceWithText(curInput);
    }

    if (!_placeholderSupport) {
        for (var i = 0; i < document.forms.length; i++) {
            var oForm = document.forms[i];
            if (oForm.attachEvent) {
                oForm.attachEvent("onsubmit", function() {
                    PlaceholderFormSubmit(oForm);
                });
            }
            else if (oForm.addEventListener)
                oForm.addEventListener("submit", function() {
                    PlaceholderFormSubmit(oForm);
                }, false);
        }
    }
};

function PlaceholderFormSubmit(oForm) {
    for (var i = 0; i < oForm.elements.length; i++) {
        var curElement = oForm.elements[i];
        HandlePlaceholderItemSubmit(curElement);
    }
}

function HandlePlaceholderItemSubmit(element) {
    if (element.name) {
        var curPlaceholder = element.getAttribute("placeholder");
        if (curPlaceholder && curPlaceholder.length > 0 && element.value === curPlaceholder) {
            element.value = "";
            window.setTimeout(function() {
                element.value = curPlaceholder;
            }, 100);
        }
    }
}

function ReplaceWithText(oPasswordTextbox) {
    if (_placeholderSupport)
        return;
    var oTextbox = document.createElement("input");
    oTextbox.type = "text";
    oTextbox.id = oPasswordTextbox.id;
    oTextbox.name = oPasswordTextbox.name;
    //oTextbox.style = oPasswordTextbox.style;
    oTextbox.className = oPasswordTextbox.className;
    for (var i = 0; i < oPasswordTextbox.attributes.length; i++) {
        var curName = oPasswordTextbox.attributes.item(i).nodeName;
        var curValue = oPasswordTextbox.attributes.item(i).nodeValue;
        if (curName !== "type" && curName !== "name") {
            oTextbox.setAttribute(curName, curValue);
        }
    }
    oTextbox.originalTextbox = oPasswordTextbox;
    oPasswordTextbox.parentNode.replaceChild(oTextbox, oPasswordTextbox);
    HandlePlaceholder(oTextbox);
    if (!_placeholderSupport) {
        oPasswordTextbox.onblur = function() {
            if (this.dummyTextbox && this.value.length === 0) {
                this.parentNode.replaceChild(this.dummyTextbox, this);
            }
        };
    }
}

function HandlePlaceholder(oTextbox) {
    if (!_placeholderSupport) {
        var curPlaceholder = oTextbox.getAttribute("placeholder");
        if (curPlaceholder && curPlaceholder.length > 0) {
            Debug("Placeholder found for input box '" + oTextbox.name + "': " + curPlaceholder);
            oTextbox.value = curPlaceholder;
            oTextbox.setAttribute("old_color", oTextbox.style.color);
            oTextbox.style.color = "#c0c0c0";
            oTextbox.onfocus = function() {
                var _this = this;
                if (this.originalTextbox) {
                    _this = this.originalTextbox;
                    _this.dummyTextbox = this;
                    this.parentNode.replaceChild(this.originalTextbox, this);
                    _this.focus();
                }
                Debug("input box '" + _this.name + "' focus");
                _this.style.color = _this.getAttribute("old_color");
                if (_this.value === curPlaceholder)
                    _this.value = "";
            };
            oTextbox.onblur = function() {
                var _this = this;
                Debug("input box '" + _this.name + "' blur");
                if (_this.value === "") {
                    _this.style.color = "#c0c0c0";
                    _this.value = curPlaceholder;
                }
            };
        }
        else {
            Debug("input box '" + oTextbox.name + "' does not have placeholder attribute");
        }
    }
    else {
        Debug("browser has native support for placeholder");
    }
}

function Debug(msg) {
    if (typeof _debug !== "undefined" && _debug) {
        var oConsole = document.getElementById("Console");
        if (!oConsole) {
            oConsole = document.createElement("div");
            oConsole.id = "Console";
            document.body.appendChild(oConsole);
        }
        oConsole.innerHTML += msg + "<br />";
    }
}