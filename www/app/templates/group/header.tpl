{* this applies binding so make sure it is outside of group header*}
{include file="dialogs/user-selector.tpl"}

<div id="group-header" class="sub-header">
    {include file="dialogs/group-report.tpl"}
    {include file="dialogs/group-update.tpl"}
    {include file="dialogs/group-requests.tpl"}
    {include file="dialogs/group-users-list.tpl"}

    <ul class="menu">
        <li class="menu-profile-icon"><img src="{$group.image|escape}" width="25px" height="25px" /></li>
        <li>
            <a href="{$base|escape}group-{$group.id|escape}/detail">
                {$group.title|escape}
                <em data-bind="visible:approvalNeeded()">[approval]</em>
            </a>
        </li>
        {if isset($group.distance)}
            <li><span class="menu-text">Distance {$group.distance|round:2|escape} miles</span></li>
        {/if}
        <li>
            <span class="menu-text">
            {if isset($group.location.country)}{$group.location.country.name|escape}{/if}
            {if isset($group.location.region)} - {$group.location.region.name|escape}{/if}
            {if isset($group.location.city)} - {$group.location.city.name|escape}{/if}
            </span>
        </li>
        <li><a data-bind="click: showMembers">Members: <span data-bind="text: members"></span></a></li>
        <li><a href="{$base|escape}group-{$group.id|escape}/feed">Posts: {$group.posts_visible_count|escape}</a></li>

        <li data-bind="visible: isMember()"><a data-bind="click: leave">Leave</a></li>
        <li data-bind="visible: !isMember() && canJoin()"><a data-bind="click: join">Join</a></li>
        <li data-bind="visible: canInteract && !isMember() && !canJoin()"><a data-bind="click: requestJoin">Request Join</a></li>
        <li><a data-bind="visible: canInteract, click: function() { showDialog('#group-report'); }">Report</a></li>
        <li data-bind="visible: isMember()"><a data-bind="click: invite">Invite</a></li>
    </ul>

    <ul data-bind="visible: canEdit()" class="menu" style="float: right;margin-left: 5px">
        <li><span class="menu-text"><strong>Admin Menu</strong></span></li>
        <li><a data-bind="click: function() { showDialog('#group-update'); }">Update</a></li>
        <li><a data-bind="click: function() { this.remove(); }">Delete</a></li>
        <li><a data-bind="click: showAdmins">Admins</a></li>
        <li><a data-bind="click: showRequests">Pending Requests</a></li>
    </ul>

    <ul class="menu">
        <li><a href="{$base|escape}group-{$group.id|escape}/feed">Feed</a></li>
        <li><a href="{$base|escape}group-{$group.id|escape}/articles">Articles</a></li>
        <li><a href="{$base|escape}group-{$group.id|escape}/events">Events</a></li>
        <li><a href="{$base|escape}group-{$group.id|escape}/gallery">Gallery</a></li>
    </ul>
</div>

<script type="text/javascript">
    var group = {$group|json_encode:$json_options};
    window.groupViewModel = new GroupViewModel(group);
    ko.applyBindings(window.groupViewModel, document.getElementById("group-header"));
</script>