{extends "../index.tpl"}
{block "title"}Groups Around Me - Activity{/block}
{block "main-content"}
    <h1>Activity</h1>

    {literal}
    <div id="activity">
        <ul class="menu">
            <li><a href="" data-bind="click: function() { this.listType('everything'); }">Everything</a></li>
            <li><a href="" data-bind="click: function() { this.listType('others_only'); }">Others</a></li>
            <li><a href="" data-bind="click: function() { this.listType('me_only'); }">Me</a></li>
        </ul>

        <div data-bind="foreach: entries">

            <div class="activity-item">
                <div data-bind="text: time" class="time"></div>

                <div data-bind="if: group" class="group">
                    <img data-bind="attr: {src: group.image}" height="25" width="25" />
                    <a data-bind="text: group.title, attr: {href: group.link}"></a>
                </div>

                <div data-bind="with: user" class="from">
                    <img data-bind="attr: {src: image}" height="25" width="25" />
                    <a data-bind="text: name, attr: {href: link}"></a>
                </div>

                <div class="text">
                    <span data-bind="text: activityText"></span>
                    <a data-bind="attr: {href: link}"><span data-bind="text: typeText"></span></a>
                </div>
            </div>

        </div>
        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
        <h2 data-bind="visible: !hasEntries() && !loading()">No activity yet.</h2>
    </div>

    <script type="text/javascript">
        ko.applyBindings(new ActivityViewModel(), document.getElementById("activity"));
    </script>
    {/literal}
{/block}