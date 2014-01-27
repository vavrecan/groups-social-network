{extends "../index.tpl"}
{block "title"}Groups Around Me - Create Group{/block}
{block "main-content"}
    <h1>Create a new group</h1>

    <div id="group-create">
        <form data-bind="submit: create">
            <div class="row">
                <input name="title" class="text" placeholder="Title" type="text" value="" />
            </div>

            <div class="row">
                <textarea name="description" class="text" placeholder="Description" type="text" value=""></textarea>
            </div>

            <div class="row">
                <input name="link" class="text" placeholder="Link" type="text" value="" />
            </div>

            <div class="row">

                <div class="tokenizer">
                    <div class="token-input">
                        <input type="hidden" name="tags" value="" />
                        <input class="text" placeholder="Tag" type="text" />
                    </div>
                </div>

            </div>

            <div class="row">
                <input name="privacy[0]" id="group-privacy" class="text" type="checkbox" value="approval_needed" />
                <label for="group-privacy">Only admin can add members</label>
            </div>

            <div class="row">
                <input name="privacy[1]" id="group-privacy-posts" class="text" type="checkbox" value="admin_posts_only" />
                <label for="group-privacy-posts">Only admin can add posts</label>
            </div>

            <div class="row">
                <input name="privacy[2]" id="group-privacy-articles" class="text" type="checkbox" value="admin_articles_only" checked="checked" />
                <label for="group-privacy-articles">Only admin can add articles</label>
            </div>

            <div class="row">
                <input name="privacy[3]" id="group-privacy-events" class="text" type="checkbox" value="admin_events_only" checked="checked" />
                <label for="group-privacy-events">Only admin can add events</label>
            </div>

            <div class="row">
                <input name="privacy[4]" id="group-privacy-galleries" class="text" type="checkbox" value="admin_galleries_only" checked="checked" />
                <label for="group-privacy-galleries">Only admin can add galleries</label>
            </div>

            <input type="submit" class="button" value="Save" />
        </form>
    </div>

    <script type="text/javascript">
        ko.applyBindings(new GroupsViewModel(), document.getElementById("group-create"));
    </script>
{/block}