<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$templates = [
'input' => '
    <tr>
        <td class="trow2" style="vertical-align: top"><strong>Tags:</strong><br><small>Tags are separated by comma.</small></td>
        <td class="trow2">
            <input type="hidden" value="{$tags_value}" name="thread-tag-ids" />
            <div id="thread-tag-badges">{$tag_badges}</div>
            <div class="autocomplete-wrapper">
                <input type="text" id="thread-tags-input" value="" />
            </div>
        </td>
    </tr>',

'badge' => '
    <div class="tagsplus-badge badge tagsplus-id-{$tag[\'tagid\']}{$tags_editable}">
        {$tag[\'name\']}
        <button type="button" class="close" data-tagname="{$tag[\'name\']}" aria-label="Remove">
            <span aria-hidden="true">×</span>
        </button>
    </div>
',

'filter' => '
    <a href="{$tag[\'link\']}" class="tagsplus-badge badge tagsplus-id-{$tag[\'tagid\']} tagsplus-filter">
        {$tag[\'name\']}
    </a>
',

'modcp_nav_edittags' => '
    <tr>
        <td class="trow1 smalltext"><a href="modcp.php?action=tags" class="modcp_nav_item modcp_nav_tags">Tags</a></td>
    </tr>',

'modcp_tags_notags' => '
    <tr id="tag-no-rows"><td colspan="5" class="trow1" align="center">No tags have been created.</td></tr>',

'modcp_tags_tagrow' => '
    <tr data-tagid="{$tag_id}">
        <td class="{$rowclass}"><div class="tagsplus-badge badge{$tag_class}">{$tag_name}</div></td>
        <td class="{$rowclass}">{$tag_slug}</td>
        <td class="{$rowclass}">{$tag_createdby}</td>
        <td class="{$rowclass}">{$tag_uses}</td>
        <td class="{$rowclass}">{$tag_actions}</td>
    </tr>',

'modcp_tags' =>'
<html>
    <head>
    <title>{$mybb->settings[\'bbname\']} - Tag Moderation</title>
    {$headerinclude}
    </head>
    <body>
        {$header}
        <table width="100%" border="0" align="center">
            <tr>
                {$modcp_nav}
                <td valign="top">
            <table border="0" id="tag-management-table" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
            <tbody><tr>
                <td class="thead" align="center" colspan="5"><strong>Tag Management</strong></td>
            </tr>
            <tr>
                <td class="tcat"><span class="smalltext"><strong>Tag name</strong></span></td>
                <td class="tcat" align="center"><span class="smalltext"><strong>Shortform</strong></span></td>
                <td class="tcat" align="center"><span class="smalltext"><strong>Created by</strong></span></td>
                <td class="tcat" align="center"><span class="smalltext"><strong>Uses</strong></span></td>
                <td class="tcat" align="center"><span class="smalltext"><strong>Actions</strong></span></td>
            </tr>
            <tr id="add-new-tag">
                <td class="trow1"><input type="text" class="textbox" name="new-tag-name" maxlength="255" /></td>
                <td class="trow1"><input type="text" class="textbox" name="new-tag-slug" maxlength="64" /></td>
                <td class="trow1"><span class="muted">{$username}</span></td>
                <td class="trow1"><span class="muted">0</span></td>
                <td class="trow1"><button type="submit">Add</button></td>
            </tr>
            {$existing_tags}
            </tbody>
            </table></td>
            </tr>
        </table>
        {$footer}
        <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/tagsplus/tagsplus-modcp.js"></script>
    </body>
</html>'
];