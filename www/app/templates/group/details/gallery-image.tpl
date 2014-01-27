{extends "../../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}
    {include file="dialogs/voting-list.tpl"}

    <div id="gallery-image">
        {literal}
        <div data-bind="with: galleryImage">
            <a data-bind="attr: {href: galleryLink}">Return to gallery</a>
            <p data-bind="text: message"></p>
            <p data-bind="text: time"></p>

            <img data-bind="attr: { src: imageFull }" style="max-width: 800px;max-height: 400px;"/>
            {/literal}{include file="controls/controls.tpl"}{literal}

        </div>
        {/literal}
    </div>
    <script type="text/javascript">
        var galleryImage = {$gallery_image|json_encode:$json_options};
        ko.applyBindings(new GalleryImageViewModel(window.groupViewModel, galleryImage), document.getElementById("gallery-image"));
    </script>
{/block}