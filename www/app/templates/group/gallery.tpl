{extends "../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="group-gallery">
        {literal}
        <div class="galery-list">
            <div data-bind="visible: canCreate()">
                <button data-bind="click: function() { showDialog('#create-gallery'); }" class="button create">Create Gallery</button>
                <div style="display: none">
                    <div id="create-gallery" class="dialog">
                        <h3>Create Gallery</h3>
                        <form data-bind="submit: create" class="padding">
                            <div class="row">
                                <input name="title" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <select name="visibility">
                                    <option value="private">private</option>
                                    <option value="public">public</option>
                                </select>
                            </div>
                            <input data-bind="css: {wait: posting()}" type="submit" class="button" value="Create" />
                        </form>
                    </div>
                </div>
            </div>

            <div class="gallery">
                <div data-bind="visible: !group.isMember()" class="disclaimer">
                    Some galleries might not be visible for public. Became member to see them all.
                </div>

                <div data-bind="foreach: entries">
                    <div class="gallery-item" data-bind="click: function() { $parent.select(id) }">
                        <img data-bind="attr: { src: image }" style="float: left;width:50px;height: 50px;padding-right: 10px" />
                        <h2><span data-bind="text: title"></span> (<span data-bind="text: imageCount"></span>)</h2>
                        <div class="content">
                            <span data-bind="with: comments">
                                Comments: <span data-bind="text: commentsCount"></span>,
                            </span>
                            <span data-bind="with: voting">
                                Likes: <span data-bind="text: likesCount"></span>,
                                Dislikes: <span data-bind="text: dislikesCount"></span>
                            </span>
                            <!--
                            <span>
                                visibility <span data-bind="text: visibility"></span>
                            </span>
                            -->
                        </div>
                            <!--
                            <span>
                                Updated <span data-bind="text: time"></span>
                            </span>
                            -->
                        <div data-bind="with: user" class="from">
                            <img data-bind="attr: {src: image}" height="25" width="25" />
                            <a data-bind="text: name, attr: {href: link}"></a>
                        </div>
                    </div>
                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
            </div>
            <h2 data-bind="visible: !hasEntries() && !loading()">Empty</h2>

        </div>

        <div data-bind="with: galleryDetail" class="gallery-detail">

            <div data-bind="if: canEdit">
                <button data-bind="click: function() { showDialog('#gallery-update'); }" class="button">Update gallery</button>
                <div style="display:none">
                    <div id="gallery-update" class="dialog">
                        <h3>Update gallery</h3>
                        <form data-bind="submit: function(form) { $parent.update(form, this); }" class="padding">
                            <div class="row">
                                <input data-bind="value: title" name="title" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <select name="visibility" data-bind="value: visibility">
                                    <option value="private">private</option>
                                    <option value="public">public</option>
                                </select>
                            </div>
                            <input data-bind="css: {wait: $parent.posting()}" type="submit" class="button" value="Update" />
                        </form>
                    </div>
                </div>
                <button data-bind="click: function() { $parent.remove(this); }" class="button">Delete gallery</button>
                <button data-bind="click: function() { showDialog('#gallery-add'); }" class="button">Add image</button>
                <div style="display:none">
                    <div id="gallery-add" class="dialog">
                        <h3>Add image to gallery</h3>
                        <form data-bind="submit: function(form) { return $parent.addImage(form, this); }" class="padding">
                            <div class="row">
                                <input name="message" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <input name="image" class="text" placeholder="Select an image" type="file" />
                            </div>
                            <input type="submit" class="button" value="Add" />
                        </form>
                    </div>
                </div>
            </div>

            <h2 data-bind="text: title"></h2>
            <div>
                Visibility <span data-bind="text: visibility"></span>
            </div>
            <div>
                Image Count <span data-bind="text: imageCount"></span>
            </div>
            <div>
                Created <span data-bind="text: created"></span>
            </div>
            <div>
                <span>
                    Updated <span data-bind="text: time"></span>
                </span>
                <span>
                    by <a data-bind="text: user.name, attr: {href: user.link}"></a>
                </span>
            </div>

            <div data-bind="with: galleryImages">
                <div>
                    <div data-bind="foreach: entries" class="gallery-images">
                        <div class="gallery-image">
                            <div data-bind="click: function() { $parent.show(this); }">
                                <img data-bind="attr: { src: image }" />
                                <h2 data-bind="text: message"></h2>
                                <div data-bind="text: time"></div>
                            </div>

                            <span data-bind="with: comments">
                                Comments: <span data-bind="text: commentsCount"></span> -
                            </span>
                            <span data-bind="with: voting">
                                Likes: <span data-bind="text: likesCount"></span> -
                                Dislikes: <span data-bind="text: dislikesCount"></span>
                            </span>

                            <div data-bind="if: canEdit">
                                <button data-bind="click: function() { $parent.remove(this); }" class="button">Delete image</button>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">There are no images</h2>
            </div>

            {/literal}{include file="controls/controls.tpl"}{literal}
        </div>

        <div data-bind="with: imageDetail">
            <div style="display:none">
                <div id="image-detail" class="dialog">
                    <div data-bind="with: image">
                        <p data-bind="text: message"></p>
                        <a data-bind="text: time, attr: {href: link}"></a>
                    </div>

                    <a data-bind="click: previousImage">prev</a>
                    <a data-bind="click: nextImage">next</a>

                    <div data-bind="with: image" style="width: 800px;height: 450px">
                        <img data-bind="attr: { src: imageFull }" style="max-width: 800px;max-height: 400px;"/>
                        {/literal}{include file="controls/controls.tpl"}{literal}
                    </div>

                </div>
            </div>
        </div>
        {/literal}
    </div>

    <script type="text/javascript">
        var galleries = new GalleriesViewModel(window.groupViewModel);
        ko.applyBindings(galleries, document.getElementById("group-gallery"));

        {if isset($gallery)}
        var gallery = {$gallery|json_encode:$json_options};
        galleries.load(gallery);
        {/if}
    </script>
{/block}