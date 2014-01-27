<div style="display: none">
    <div id="user-report" class="dialog">
        <h3>Report User - <span data-bind="text: name"></span></h3>
        <div class="padding">
            <form data-bind="submit: report">
                <div class="row">
                    <textarea name="message" placeholder="Reason for reporting"></textarea>
                </div>
                <input type="submit" class="button" />
            </form>
        </div>
    </div>
</div>