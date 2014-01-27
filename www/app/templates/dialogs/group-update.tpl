<div style="display:none">
    <div id="group-update" class="dialog">
        <h3>Update group</h3>
        <div class="padding">

            <form data-bind="submit: update">
                <div class="row">
                    <input name="title" class="text" value="{$group.title|escape}" placeholder="Title" type="text" />
                </div>

                <div class="row">
                    <textarea name="description" class="text" placeholder="Description" type="text">{$group.description|escape}</textarea>
                </div>

                <div class="row">
                    <input name="link" class="text" placeholder="Link" type="text" value="{$group.link|escape}" />
                </div>

                <div class="row">
                    <div class="tokenizer">
                        <div class="token-input">
                            <input type="hidden" name="tags" value="{$group.tags|json_encode:$json_options|escape}" />
                            <input class="text" placeholder="Tag" type="text" />
                        </div>
                    </div>
                </div>

                <input name="privacy" type="hidden" value="" />

                <div class="row">
                    <input name="privacy[0]" id="group-privacy" class="text" type="checkbox" value="approval_needed"
                           {if in_array("approval_needed", $group.privacy)}checked="checked"{/if} />
                    <label for="group-privacy">Only admin can add members</label>
                </div>

                <div class="row">
                    <input name="privacy[1]" id="group-privacy-posts" class="text" type="checkbox" value="admin_posts_only"
                           {if in_array("admin_posts_only", $group.privacy)}checked="checked"{/if} />
                    <label for="group-privacy-posts">Only admin can add posts</label>
                </div>

                <div class="row">
                    <input name="privacy[2]" id="group-articles-posts" class="text" type="checkbox" value="admin_articles_only"
                           {if in_array("admin_articles_only", $group.privacy)}checked="checked"{/if} />
                    <label for="group-articles-posts">Only admin can add articles</label>
                </div>

                <div class="row">
                    <input name="privacy[3]" id="group-events-posts" class="text" type="checkbox" value="admin_events_only"
                           {if in_array("admin_events_only", $group.privacy)}checked="checked"{/if} />
                    <label for="group-events-posts">Only admin can add events</label>
                </div>

                <div class="row">
                    <input name="privacy[4]" id="group-galleries-posts" class="text" type="checkbox" value="admin_galleries_only"
                           {if in_array("admin_galleries_only", $group.privacy)}checked="checked"{/if} />
                    <label for="group-galleries-posts">Only admin can add galleries</label>
                </div>

                <input type="submit" class="button" value="Save" />
            </form>

        </div>

        <h3>Profile Image</h3>
        <div class="padding">
            <form data-bind="submit: setImage">
                <div class="row">
                    <input name="image" class="text" placeholder="Select an image" type="file" />
                </div>
                <input type="submit" class="button" value="Save" />
            </form>

            <form data-bind="submit: removeImage">
                <input type="submit" class="button" value="Remove" />
            </form>
        </div>

        <h3>Set Location</h3>
        <div class="padding">

            <form data-bind="submit: updateLocation">
                <div class="row">
                    <input name="latitude" class="text" placeholder="Latitude" type="text" value="{$group.location.latitude|escape}" />
                </div>
                <div class="row">
                    <input name="longitude" class="text" placeholder="Longitude" type="text" value="{$group.location.longitude|escape}" />
                </div>
                <input type="submit" class="button" value="Save" />
            </form>

        </div>

    </div>
</div>