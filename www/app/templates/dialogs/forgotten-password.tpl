<div style="display:none">
    <div id="forgotten-password" class="dialog">
        <h3>Forgotten Password</h3>
        <div class="padding">
            <p>In order to verify your account, you have to enter your E-mail where you will receive confirmation link.</p>
            <form method="post" action="{$base|escape}auth/forgotten-password" class="ajax-form">
                <input type="text" name="email" class="text" placeholder="Enter your E-mail" />
                <input type="submit" class="button" value="Send" />
            </form>
        </div>
    </div>
</div>