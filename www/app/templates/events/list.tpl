{extends "../index.tpl"}
{block "title"}Groups Around Me - Events{/block}
{block "main-content"}
    <h1>Events</h1>

    {literal}
        <div id="events-attending">
            <div data-bind="foreach: entries">

                <div class="event-item">
                    <div class="users" data-bind="with: users">
                        <span data-bind="text: goingCount() + maybeCount()"></span>
                        attending
                        <!--  Maybe: <span data-bind="text: maybeCount"></span>
                        <!-- Not Going: <span data-bind="text: notGoingCount"></span> -->
                    </div>

                    <a data-bind="attr: {href: link}">
                        <span data-bind="text: title"></span>
                        <span data-bind="if: attending == 'going'">
                            (going)
                        </span>
                        <span data-bind="if: attending == 'maybe'">
                            (maybe)
                        </span>
                    </a>

                    <div class="detail">
                        <span data-bind="if: time">
                            from <span data-bind="text: time"></span>
                        </span>
                        <span data-bind="if: timeEnd">
                            until <span data-bind="text: timeEnd"></span>
                        </span>
                    </div>
                </div>

            </div>
            <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>

            <h2 data-bind="visible: !hasEntries() && !loading()">Not attending any event</h2>
        </div>

        <script type="text/javascript">
            ko.applyBindings(new AttendingEventsViewModel(), document.getElementById("events-attending"));
        </script>
    {/literal}
{/block}