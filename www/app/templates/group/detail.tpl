{extends "../index.tpl"}
{block "title"}Groups Around Me - {$group.title|escape}{/block}
{block "main-content"}
    {include file="group/header.tpl"}

    <p>{$group.description|escape}</p>
    <ul>
        {foreach from=$group.tags item=tag}
            <li>{$tag|escape}</li>
        {/foreach}
    </ul>

    <ul>
        <li>Created: {$group.created|escape}</li>
        <li>Created by: <a href="{$base|escape}user-{$group.user.id|escape}">{$group.user.name|escape}</a></li>
        <li>Link: <a href="{$group.link|escape}" target="_blank">{$group.link|escape}</a></li>
    </ul>

    Permissions
    <ul>
        {if in_array("approval_needed", $group.privacy)}<li>Approval from admins needed to join this group</li>{/if}
        {if in_array("admin_posts_only", $group.privacy)}<li>Only admins can add post</li>{/if}
        {if in_array("admin_articles_only", $group.privacy)}<li>Only admins can add article</li>{/if}
        {if in_array("admin_events_only", $group.privacy)}<li>Only admins can add event</li>{/if}
        {if in_array("admin_galleries_only", $group.privacy)}<li>Only admins can add gallery</li>{/if}
    </ul>
{/block}