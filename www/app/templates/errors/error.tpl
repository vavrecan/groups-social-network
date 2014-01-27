{extends "../index.tpl"}
{block "title"}Groups Around Me - Error{/block}
{block "main-content"}

    <div class="content-block error">

        <h1>Error processing your request</h1>
        {if isset($error) }
            <p>{$error.message|escape}</p>
            <p>{$error.file|escape}:{$error.line|escape}</p>
        {else}
            <p>{$message|escape}</p>
        {/if}

    </div>

{/block}