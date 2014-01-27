{extends "../../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="group-post">
        {literal}
        <div data-bind="with: post">
            <div data-bind="css: {unread: !read}" class="feed-item">
                <a class="time" data-bind="text: created, attr: { href: link }"></a>

                <span data-bind="text: visibility" style="float:right;padding:5px;color:#BBBBBB"></span>

                <div class="user" data-bind="if: user">
                    <img data-bind="attr: {src: user.image}" height="20" width="20" />
                    <a data-bind="text: user.name, attr: {href: user.link}"></a>
                </div>

                <div class="message" data-bind="visible: message != '', text: message"></div>
                <!--div class="type" data-bind="text: type" style="color:#a39fa1;padding:10px"></div-->

                <div data-bind="with: extra">
                    <div class="extra">
                        <span data-bind="text: type"></span>: <a data-bind="text: title, attr: { href: link }"></a>
                    </div>
                </div>

                <div data-bind="visible: image">
                    <div class="extra-image">
                        <img data-bind="attr: {'src': image}, click: function() { $parent.showImage(this) }" />
                    </div>
                </div>

                <div data-bind="with: linkDetail">
                    <div class="link">
                        <img data-bind="attr: { src: image } " />
                        <div class="link-info">
                            <strong><a data-bind="text: title, attr: { href: url }" target="_blank"></a></strong>
                            <div data-bind="text: description"></div>
                            <div data-bind="text: host"></div>
                        </div>
                    </div>
                </div>

                {/literal}{include file="controls/controls.tpl"}{literal}
            </div>
        </div>

        <div style="display:none">
            <div id="image-detail" class="dialog">
                <div style="width: 800px;height: 800px">
                    <img data-bind="attr: { src: imageDetail }" style="max-width: 800px;max-height: 800px;"/>
                </div>
            </div>
        </div>
        {/literal}
    </div>
    <script type="text/javascript">
        var post = {$post|json_encode:$json_options};
        ko.applyBindings(new PostViewModel(window.groupViewModel, post), document.getElementById("group-post"));
    </script>
{/block}