{literal}
<ul class="options-empty">
    <li data-bind="with: comments">
        <a data-bind="visible: commentsCount() > 0, text: commentText, click: comment"></a>
    </li>
    <li data-bind="with: voting">
        <a data-bind="visible: likesCount() > 0, text: likeText, click: function () { showLikes(); }"></a>
    </li>
    <li data-bind="with: voting">
        <a data-bind="visible: dislikesCount() > 0, text: dislikeText, click: function () { showDislikes(); }"></a>
    </li>
</ul>

<ul class="options" data-bind="visible: canInteract">
    <li data-bind="with: comments">
        <a data-bind="click: comment">Comment</a>
    </li>
    <li data-bind="with: voting">
        <a data-bind="click: like">Like</a>
    </li>
    <li data-bind="with: voting">
        <a data-bind="click: dislike">Dislike</a>
    </li>
</ul>

<div data-bind="with: comments" class="comments">
    <div data-bind="if: show">
        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Previous comments</button>

        <div data-bind="foreach: entries">
            <div class="comment-item">
                <div class="time" data-bind="text: created"></div>

                <div class="user">
                    <img data-bind="attr: {src: user.image}" height="20" width="20" />
                    <a data-bind="text: user.name, attr: {href: user.link}"></a>
                </div>

                <a class="close" data-bind="if: canEdit, click: function() { $parent.remove(this) }">[x]</a>

                <div class="options" data-bind="with: voting">
                    <a data-bind="visible: canInteract, click: like">Like</a>
                    <a data-bind="visible: canInteract, click: dislike">Dislike</a>
                    <a data-bind="visible: likesCount() > 0, text: likeText, click: function () { showLikes(); }"></a>
                    <a data-bind="visible: dislikesCount() > 0, text: dislikeText, click: function () { showDislikes(); }"></a>
                </div>

                <div class="message" data-bind="text: message"></div>

            </div>
        </div>

        <form data-bind="submit: submit, visible: canInteract" method="post">
            <div>
                <input data-bind="css: {wait: posting()}" type="text" name="message" value="" placeholder="Message" />
            </div>
        </form>

    </div>
</div>
{/literal}