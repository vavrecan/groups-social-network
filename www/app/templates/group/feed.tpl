{extends "../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="group-feed">

        {literal}
        <div data-bind="visible: canPost() && !showAddPost()">
            <button data-bind="click: function() { showAddPost(true); }" class="button create">Add new post</button>
        </div>

        <form data-bind="submit: submit, visible: canPost() && showAddPost()" class="submit-post">
            <div class="row">
                <textarea data-bind="event: { change: function(k, e) { checkLink(e.target.value); }, keyup: function(k, e) { if (e.keyCode == 32 || e.keyCode == 13 || (e.ctrlKey && e.keyCode == 86)) checkLink(e.target.value); } }" name="message" class="text" placeholder="Message" type="text"></textarea>
            </div>
            <div class="row">
                <h4 data-bind="visible: attachedUrlLoading">Loading...</h4>
                <div data-bind="with: attachedUrlDetail">

                    <div class="link"">
                        <input data-bind="value: id" type="hidden" name="link_id" />
                        <img data-bind="attr: { src: image } " style="max-height: 100px; max-width: 100px;float: left;" />
                        <div class="link-info">
                            <strong><a data-bind="text: title, attr: { href: url }"></a></strong>
                            <div data-bind="text: description"></div>
                            <div data-bind="text: host"></div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="row image-attachment">
                Add photo <input name="image" class="text" placeholder="Select an image" type="file" />
            </div>
            <div class="row submit">
                <div class="admin-post">
                    <input type="checkbox" name="post_as_admin" value="1" style="float:left" /> send as admin
                </div>
                <select name="visibility">
                    <option value="private">private</option>
                    <option value="public">public</option>
                </select>
                <input data-bind="css: {wait: posting()}" type="submit" class="button" value="Send" />
            </div>
        </form>


        <div class="feed">
            <div data-bind="visible: !group.isMember()" class="disclaimer">
                Some posts might not be visible for public. Became member to see them all.
            </div>


            <div data-bind="foreach: entries">

                <div data-bind="css: {unread: !read}" class="feed-item">
                    <a class="time" data-bind="text: created, attr: { href: link }"></a>

                    <div class="more">
                        <a onclick="this.nextElementSibling.style.display = 'block';">more</a>
                        <ul>
                            <li data-bind="visible: canEdit"><a class="close" data-bind="click: function() { $parent.remove(this) }">delete</a></li>
                            <li data-bind="visible: canEdit && visibility() == 'private'"><a data-bind="click: function() { $parent.changeVisibility(this, 'public') }">set public</a></li>
                            <li data-bind="visible: canEdit && visibility() == 'public'"><a data-bind="click: function() { $parent.changeVisibility(this, 'private') }">set private</a></li>
                        </ul>
                    </div>

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
            <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
        </div>
        <h2 data-bind="visible: !hasEntries() && !loading()">Empty</h2>

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
        ko.applyBindings(new FeedViewModel(window.groupViewModel), document.getElementById("group-feed"));
    </script>
{/block}