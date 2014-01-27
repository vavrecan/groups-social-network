{literal}
<div style="display: none">
    <div id="group-requests" class="dialog" data-bind="with: requests">
        <h3>Pending Requests</h3>

        <div class="padding">

                <div class="user-list">
                    <div class="users">
                        <div data-bind="foreach: entries">

                            <div class="item-name">
                                <img data-bind="attr: {src: userFrom.image}" height="20" width="20" />
                                <a data-bind="text: userFrom.name, attr: {href: userFrom.link}"></a>

                                <span data-bind="text: typeText"></span>

                                <span data-bind="visible: userTo">
                                    <img data-bind="attr: {src: userTo.image}" height="20" width="20" />
                                    <a data-bind="text: userTo.name, attr: {href: userTo.link}"></a>
                                </span>

                                <div data-bind="text: created"></div>

                                <a data-bind="click: function() { $parent.accept(this) }, visible: type == 'request_join'">[y]</a>
                                <a data-bind="click: function() { $parent.cancel(this) }">[x]</a>
                            </div>

                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">There are no pending requests</h2>
                </div>

        </div>
    </div>
</div>
{/literal}