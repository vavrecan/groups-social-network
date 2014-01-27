{literal}
<div style="display: none">
    <div id="voting-detail" class="dialog">
        <h3 data-bind="visible: votingType() == 'like'">Likes</h3>
        <h3 data-bind="visible: votingType() == 'dislike'">Dislikes</h3>
        <div class="padding">

            <div data-bind="foreach: entries">
                <div class="item-name">
                    <div class="user" data-bind="if: user">
                        <img data-bind="attr: {src: user.image}" height="20" width="20" />
                        <a data-bind="text: user.name, attr: {href: user.link}"></a>
                    </div>
                    <div class="time" data-bind="text: created"></div>
                </div>
            </div>
            <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>

        </div>
    </div>
</div>

<script type="text/javascript">
    window.votingDetail = new VotingListViewModel();
    ko.applyBindings(window.votingDetail, document.getElementById("voting-detail"));
</script>
{/literal}