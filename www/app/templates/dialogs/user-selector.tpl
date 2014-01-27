{literal}
<div style="display:none">
    <div id="user-selector" class="dialog">
        <h3>Select user</h3>
        <div class="padding">
            <div id="user-list">

                <form data-bind="submit: searchEmail">
                    <input type="text" name="email" value="" placeholder="Search by email">
                    <input type="submit" class="button" value="Search" />
                </form>

                <h4>Following</h4>
                <div class="user-list">

                    <input data-bind="value: name, valueUpdate: 'afterkeydown'" type="text" placeholder="Search by name">
                    <div class="users">
                        <div data-bind="foreach: entries">

                            <div class="item-name">
                                <img data-bind="attr: {src: image}" height="20" width="20" />
                                <a data-bind="text: name, click: function() { $parent.select(this) }"></a>
                            </div>

                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">No result</h2>

                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        window.userSelectModel = new UserSelectViewModel();
        ko.applyBindings(window.userSelectModel, document.getElementById("user-selector"));
    </script>
</div>
{/literal}