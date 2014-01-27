{extends "index.tpl"}
{block "title"}Groups Around Me - Message{/block}
{block "main-content"}
    <h1>{$title|default:""|escape}</h1>
    <p>{$message|default:""|escape}</p>
{/block}