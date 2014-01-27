<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{block "title"}Groups Around Me{/block}</title>

    <meta property="og:title" content="{block "title"}Groups Around Me{/block}" />
    <meta property="og:description" content="{block "description"}{/block}" />
    <meta property="og:type" content="{block "og-type"}website{/block}" />
    <meta property="og:url" content="{block "og-url"}{$site_url|escape}{/block}" />
    <meta property="og:image" content="{block "og-image"}{$base_assets|escape}images/logo/logo-512x512.png{/block}" />

    <meta name="description" content="{block "description"}{/block}">
    <link rel="stylesheet" href="{$base_assets|escape}css/style.css">
    <!--[if lt IE 9]>
    <link rel="stylesheet" href="{$base_assets|escape}css/style-ie8.css">
    <script type="text/javascript" src="{$base_assets|escape}js/ie8/placeholder.js"></script>
    <![endif]-->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script type="text/javascript">
        var BASE_PATH = {$base|json_encode:$json_options};
        var BASE_ASSETS_PATH = {$base_assets|json_encode:$json_options};
        var API_URL = BASE_PATH + "api";
    </script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js"></script>
    <script type="text/javascript" src="{$base_assets|escape}js/jquery.libs.min.js"></script>
    <script type="text/javascript" src="{$base_assets|escape}js/knockout.js"></script>
    <script type="text/javascript" src="{$base_assets|escape}js/models.js"></script>
    <script type="text/javascript" src="{$base_assets|escape}js/main.js"></script>
    {block "headers"}{/block}
</head>
<body>
    <script type="text/javascript">
    window.user = {$user|json_encode:$json_options};
    </script>
    {include file="dialogs/message.tpl"}
    {include file="dialogs/forgotten-password.tpl"}
    <div class="header">
        <a href="{$base|escape}" id="logo">Groups Around Me</a>
        {if isset($user)}
            <ul class="menu" id="logged-user-menu">
                <li><a href="{$base|escape}" class="{if $path_action == 'groups/list'} active{/if}">Home</a></li>
                <li><a href="{$base|escape}search" class="{if $path_action == 'search'} active{/if}">Search</a></li>
                <li>
                    <a href="{$base|escape}messages" class="{if $path_action == 'messages'} active{/if}">
                        Messages
                        <span data-bind="text: unreadMessages, visible: unreadMessages() > 0" style="background:#990000;color:#fff;padding:0 5px;border-radius:4px;margin-left:5px"></span>
                    </a>
                </li>
                <li><a href="{$base|escape}activity" class="{if $path_action == 'activity'} active{/if}">Activity</a></li>
                <li>
                    <a href="{$base|escape}notifications" class="{if $path_action == 'notifications'} active{/if}">
                        Notifications
                        <span data-bind="text: unreadNotifications, visible: unreadNotifications() > 0" style="background:#990000;color:#fff;padding:0 5px;border-radius:4px;margin-left:5px"></span>
                    </a>
                </li>
                <li class="menu-profile-icon"><img src="{$user.image|escape}" width="30px" height="30px" /></li>
                <li><a href="{$base|escape}profile">{$user.name|escape}</a></li>
            </ul>
            <script type="text/javascript">
                ko.applyBindings(new LoggedUserDetailModel(), document.getElementById("logged-user-menu"));
            </script>

            {*
            <div class="header-profile">

                <ul class="menu">
                    <li>
                        {if isset($user.location.country)}{$user.location.country.name|escape}{/if}
                        {if isset($user.location.region)} - {$user.location.region.name|escape}{/if}
                        {if isset($user.location.city)} - {$user.location.city.name|escape}{/if}
                    </li>
                    <li><a href="{$base|escape}auth/logout">Logout</a></li>
                </ul>

            </div> *}
        {else}
            <form method="post" action="{$base|escape}auth/login" id="login-form" class="ajax-form">
                <div>
                    <input name="email" type="text" class="text" placeholder="E-mail" />
                    <input name="password" type="password" class="text" placeholder="Password" />
                    <input type="submit" class="button" value="Log In" />
                </div>
                <div>
                    <input type="checkbox" name="keep-logged-in" id="keep-logged-in" value="1" checked="checked" />
                    <label for="keep-logged-in">Keep me logged in</label>
                    <a href="#forgotten-password" class="open-dialog">Forgot your password?</a>
                </div>
            </form>
        {/if}
    </div>

    {block "content"}
    <div class="content">
        {block "main-content"}{/block}
    </div>
    {/block}

    <div class="footer">
        <ul>
            <li><a href="{$base|escape}terms">Terms &amp; Conditions</a></li>
            <li><a href="{$base|escape}contact">Contact</a></li>
        </ul>
    </div>
</body>
</html>


