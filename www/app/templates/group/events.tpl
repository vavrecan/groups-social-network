{extends "../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="group-events">
        {literal}

        <div class="events-list">
            <button data-bind="visible: canCreate(), click: function() { showDialog('#create-event'); }" class="button create">Create Event</button>
            <div style="display: none">
                <div id="create-event" class="dialog">
                    <h3>Create Event</h3>
                    <form data-bind="submit: create" class="padding">
                        <div class="row">
                            <input name="title" class="text" placeholder="Title" type="text"/>
                        </div>
                        <div class="row">
                            <textarea name="message" class="text" placeholder="Message" type="text"></textarea>
                        </div>
                        <div class="row">
                            <input name="time" class="text" placeholder="Time" type="text"/>
                        </div>
                        <div class="row">
                            <input name="time_end" class="text" placeholder="Time End" type="text"/>
                        </div>
                        <div class="row">
                            <input name="location_title" class="text" placeholder="Location description" type="text"/>
                        </div>
                        <div class="row">
                            <input name="latitude" class="text" placeholder="Latitude" type="text"/>
                        </div>
                        <div class="row">
                            <input name="longitude" class="text" placeholder="Longitude" type="text"/>
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

            <div class="events">
                <div data-bind="visible: !group.isMember()" class="disclaimer">
                    Some events might not be visible for public. Became member to see them all.
                </div>

                <div data-bind="foreach: entries">

                    <div class="group-event-item" data-bind="click: function() { $parent.select(id) }">
                        <h2 data-bind="text: title"></h2>

                        <div class="detail">

                            <div>
                                <span>
                                    Time from <span data-bind="text: time"></span> until <span data-bind="text: timeEnd"></span>
                                </span>
                            </div>

                            <div>
                                <span data-bind="with: users">
                                    Going: <span data-bind="text: goingCount"></span>,
                                    Maybe: <span data-bind="text: maybeCount"></span>,
                                    Not Going: <span data-bind="text: notGoingCount"></span>
                                </span>
                            </div>

                            <div>
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

                </div>
                <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
            </div>
            <h2 data-bind="visible: !hasEntries() && !loading()">Empty</h2>
        </div>

        <div data-bind="with: eventUsersDetail" style="display:none">
            <div id="event-users-detail" class="dialog">
                <h3 data-bind="visible: attendanceType() == 'going'">Going</h3>
                <h3 data-bind="visible: attendanceType() == 'maybe'">Maybe</h3>
                <h3 data-bind="visible: attendanceType() == 'not_going'">Not going</h3>
                <div class="padding">

                    <div data-bind="foreach: entries">
                        <div class="item-name">
                            <div class="user" data-bind="if: user">
                                <img data-bind="attr: {src: user.image}" height="20" width="20" />
                                <a data-bind="text: user.name, attr: {href: user.link}"></a>
                            </div>
                        </div>
                    </div>
                    <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>

                </div>
                <h2 data-bind="visible: !hasEntries() && !loading()">No user attending</h2>
            </div>
        </div>

        <div data-bind="with: eventDetail" class="event-detail">
            <div data-bind="if: canEdit">
                <button data-bind="click: function() { showDialog('#update-event'); }" class="button">Update event</button>
                <div style="display: none">
                    <div id="update-event" class="dialog">
                        <h3>Update event</h3>
                        <form data-bind="submit: function(form) { $parent.update(form, this); }" class="padding">
                            <div class="row">
                                <input data-bind="value: title" name="title" class="text" placeholder="Title" type="text"/>
                            </div>
                            <div class="row">
                                <textarea data-bind="value: message" name="message" class="text" placeholder="Message" type="text"></textarea>
                            </div>
                            <div class="row">
                                <input data-bind="value: time" name="time" class="text" placeholder="Time" type="text"/>
                            </div>
                            <div class="row">
                                <input data-bind="value: timeEnd" name="time_end" class="text" placeholder="Time End" type="text"/>
                            </div>
                            <div class="row">
                                <input data-bind="value: location.title" name="location_title" class="text" placeholder="Location description" type="text"/>
                            </div>
                            <div class="row">
                                <input data-bind="value: location.latitude" name="latitude" class="text" placeholder="Latitude" type="text"/>
                            </div>
                            <div class="row">
                                <input data-bind="value: location.longitude" name="longitude" class="text" placeholder="Longitude" type="text"/>
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

                <button data-bind="click: function() { $parent.remove(this); }" class="button">Delete event</button>
            </div>

            <h1 data-bind="text: title"></h1>
            <div data-bind="text: message"></div>
            <div>
                Time from <span data-bind="text: time"></span> until <span data-bind="text: timeEnd"></span>
            </div>

            <div data-bind="with: users" class="padding">
                <a data-bind="click: function () { showGoing($root.eventUsersDetail); }">Going: <span data-bind="text: goingCount"></span></a>  -
                <a data-bind="click: function () { showMaybe($root.eventUsersDetail); }">Maybe: <span data-bind="text: maybeCount"></span></a> -
                <a data-bind="click: function () { showNotGoing($root.eventUsersDetail); }">Not Going: <span data-bind="text: notGoingCount"></span></a> -
            </div>

            <div data-bind="if: canInteract">
                <div data-bind="if: attending() == null" class="padding">
                    Attend
                    <button data-bind="click: function() { $parent.going(this); }">Going</button>
                    <button data-bind="click: function() { $parent.maybe(this); }">Maybe</button>
                    <button data-bind="click: function() { $parent.notGoing(this); }">Not Going</button>
                </div>

                <div data-bind="if: attending() == 'going'">
                    you are going to this event
                    <button data-bind="click: function() { $parent.miss(this); }">Change</button>
                </div>
                <div data-bind="if: attending() == 'maybe'">
                    maybe you will come
                    <button data-bind="click: function() { $parent.miss(this); }">Change</button>
                </div>
                <div data-bind="if: attending() == 'not_going'">
                    you wont come
                    <button data-bind="click: function() { $parent.miss(this); }">Change</button>
                </div>
            </div>

            <div data-bind="if: location">
                Location
                <span><span data-bind="text: location.title"></span></span>
                <span><span data-bind="text: location.longitude"></span></span>
                <span><span data-bind="text: location.latitude"></span></span>
            </div>

            <div class="padding" style="overflow:hidden;clear:both">
                <div style="float:right">
                    <div class="padding">Visibility: <span data-bind="text: visibility"></span></div>
                    <div class="padding">Created on <span data-bind="text: created"></span></div>
                    <div class="user" data-bind="if: user">
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
        var events = new EventsViewModel(window.groupViewModel);
        ko.applyBindings(events, document.getElementById("group-events"));

        {if isset($event)}
        var event = {$event|json_encode:$json_options};
        events.load(event);
        {/if}
    </script>
{/block}