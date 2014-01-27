{extends "index.tpl"}
{block "title"}Groups Around Me - Home{/block}
{block "main-content"}

    <h2><strong>Sign In</strong> for Free</h2>
    <form method="post" action="{$base|escape}auth/register" id="register-form" class="ajax-form" autocomplete="off">
        <div class="row">
            <input name="first_name" class="text" placeholder="First Name" type="text" />
            <input name="last_name" class="text" placeholder="Last Name" type="text" />
        </div>
        <div class="row">
            <input name="email" class="text" placeholder="E-mail" type="text" />
        </div>
        <div class="row">
            <input name="password" class="text" placeholder="Password" type="password" />
        </div>
        <div class="row">
            <input name="password_again" class="text" placeholder="Re-enter Password" type="password" />
        </div>
        <div class="row">
            <strong>Date of Birth</strong>
        </div>

        <div class="row">
            <select name="month">
                <option value="" selected="selected">Month</option>
                {foreach from=$months item=month_name key=month_id}
                    <option value="{$month_id|escape}">{$month_name|escape}</option>
                {/foreach}
            </select>
            <select name="day">
                <option value="" selected="selected">Day</option>
                {foreach from=$days item=day}
                    <option value="{$day|escape}">{$day|escape}</option>
                {/foreach}
            </select>
            <select name="year">
                <option value="" selected="selected">Year</option>
                {foreach from=$years item=year name=years}
                    <option value="{$year|escape}">{$year|escape}</option>
                {/foreach}
            </select>
        </div>

        <div class="row">
            <select name="gender" class="gender-select">
                <option value="">Select your gender</option>
                {foreach from=$genders key=gender_id item=gender_name}
                    <option value="{$gender_id|escape}">{$gender_name|escape}</option>
                {/foreach}
            </select>
        </div>

        <div class="row note">
            By signing up, you agree to the <a href="{$base|escape}terms">Terms of Use</a>.
        </div>

        <input type="submit" class="sign-button" value="Sign in" />
    </form>
{/block}