{extends "../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="group-articles">
        {literal}

        <div class="article-list">
            <div data-bind="visible: canCreate()">
                <button data-bind="click: function() { showDialog('#article-create'); }" class="button create">Create article</button>
                <div style="display:none">
                    <div id="article-create" class="dialog">
                        <h3>Create article</h3>
                        <form data-bind="submit: create" class="padding">
                            <div class="row">
                                <input name="title" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <textarea name="contents" class="text" placeholder="Contents" type="text" style="width: 600px;height: 400px"></textarea>
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

            <div class="articles">
                <div data-bind="visible: !group.isMember()" class="disclaimer">
                    Some articles might not be visible for public. Became member to see them all.
                </div>

                <div data-bind="foreach: entries">

                    <div class="article-item" data-bind="click: function() { $parent.select(id) }">
                        <h2 data-bind="text: title"></h2>
                        <div class="detail">

                            <!--
                            <span>
                                Created <span data-bind="text: created"></span> -
                            </span>
                            -->

                            <span data-bind="with: comments">
                                Comments: <span data-bind="text: commentsCount"></span>,
                            </span>
                            <span data-bind="with: voting">
                                Likes: <span data-bind="text: likesCount"></span>,
                                Dislikes: <span data-bind="text: dislikesCount"></span>
                            </span>

                            <div data-bind="with: user" class="from">
                                <img data-bind="attr: {src: image}" height="25" width="25" />
                                <a data-bind="text: name, attr: {href: link}"></a>
                            </div>

                        </div>
                    </div>

                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
            </div>
            <h2 data-bind="visible: !hasEntries() && !loading()">Empty</h2>
        </div>

        <div class="article-detail" data-bind="with: articleDetail">

            <div data-bind="visible: canEdit">
                <button data-bind="click: function() { showDialog('#article-update'); }" class="button">Update article</button>
                <div style="display:none">
                    <div id="article-update" class="dialog">
                        <h3>Update article</h3>
                        <form data-bind="submit: function(form) { $parent.update(form, id); }" class="padding">
                            <div class="row">
                                <input data-bind="value: title" name="title" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <textarea data-bind="value: contents" name="contents" class="text" placeholder="Contents" type="text" style="width: 600px;height: 400px"></textarea>
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

                <button data-bind="click: function() { $parent.remove(id); }" class="button">Delete article</button>
            </div>

            <h1 data-bind="text: title"></h1>
            <div data-bind="html: contents"></div>

            <div class="padding" style="overflow:hidden;clear:both">
                <div style="float:right;text-align: right">
                    <div>Visibility: <span data-bind="text: visibility"></span></div>
                    <div>Posted on <span data-bind="text: created"></span></div>
                    <div class="user" data-bind="if: user" style="padding: 10px 0 0 0 !important">
                        <img data-bind="attr: {src: user.image}" height="20" width="20" />
                        <a data-bind="text: user.name, attr: {href: user.link}"></a>
                    </div>
                </div>
            </div>

            {/literal}{include file="controls/controls.tpl"}{literal}
        </div>
        {/literal}
    </div>

    <script type="text/javascript">
        var articles = new ArticlesViewModel(window.groupViewModel);
        ko.applyBindings(articles, document.getElementById("group-articles"));

        {if isset($article)}
        var article = {$article|json_encode:$json_options};
        articles.load(article);
        {/if}
    </script>
{/block}