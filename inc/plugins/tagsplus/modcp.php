<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}
// modcp navigation link
$plugins->add_hook('modcp_nav', 'tagsplus_show_modtags');
function tagsplus_show_modtags() {
    global $mybb, $templates;
    // template variables
    global $nav_edittags;
    if ($mybb->usergroup['canmanagetags'] == 1) {
        eval("\$nav_edittags = \"".$templates->get("tagsplus_modcp_nav_edittags")."\";");
    }
}

/**
 * Show the modcp edit tags page
 * GET: modcp.php?action=tags
 */
$plugins->add_hook('modcp_start', 'tagsplus_edit_tags');
function tagsplus_edit_tags() {
    global $db, $lang, $mybb, $templates;
    // template vars
    global $existing_tags, $username, $theme, $header, $headerinclude, $modcp_nav, $footer;

    if ($mybb->request_method !== 'get' || $mybb->get_input('action') !== 'tags') {
        return;
    }
    add_breadcrumb($lang->nav_modcp, "modcp.php");

    if ($mybb->usergroup['canmanagetags'] == 0) {
        error_no_permission();
    }
    add_breadcrumb('Tag Management', 'modcp.php?action=tags');

    // add new tag row
    $username = $mybb->user['username'];

    // existing tags
    // todo: in theory this should be paginated, although idk, maybe people like having 1000 tags on one page o_o
    $query = $db->write_query("SELECT t.*, u.username, COUNT(pt.ptid) as uses from `mybb_tags` as t
        LEFT JOIN `{$db->table_prefix}users` as u on u.uid=t.uid
        left join `{$db->table_prefix}posttags` as pt on pt.tagid=t.tagid
        group by t.tagid");
    if ($db->num_rows($query) == 0) {
        eval("\$existing_tags = \"".$templates->get("tagsplus_modcp_tags_notags")."\";");
    }
    $tag_count = 0;
    while ($tag = $db->fetch_array($query)) {
        $tag_count++;
        $existing_tags .= TagsPlus::generate_tag_row($tag, $tag_count);
    }

    $tagscontent = '';
    eval("\$tagscontent = \"".$templates->get("tagsplus_modcp_tags")."\";");
	output_page($tagscontent);
}

/**
 * Create/Delete/Update a tag
 * POST: xmlhttp.php?action=tagsplus
 */
$plugins->add_hook('xmlhttp', 'tagsplus_moderate_tag');
function tagsplus_moderate_tag() {
    global $mybb, $db;
    if ($mybb->request_method !== 'post' || $mybb->get_input('action') !== 'tagsplus') {
        return;
    }
    if (!isset($mybb->user['uid']) || $mybb->usergroup['canmanagetags'] == 0) {
        TagsPlus::json_response(405, "No permission");
    }
    /**
     * Create a tag
     * POST: xmlhttp.php?action=tagsplus&do=create
     */
    if ($mybb->get_input('do') === 'create') {
        if (!isset($mybb->input['name'])) {
            return TagsPlus::json_response(403, "Name field is not set");
        }
        $name = $mybb->input['name'];
        $slug = $mybb->input['slug'];
        if (TagsPlus::get_tag_by_name($name) != null) {
            return TagsPlus::json_response(403, "Tag already exists");
        }
        // create the tag
        $tag = [
            'uid' => $mybb->user['uid'],
            'name' => $db->escape_string($name),
            'slug' => $db->escape_string($slug),
            'enabled' => '1',
            'dateline' => TIME_NOW
        ];
        $tagid = $db->insert_query('tags', $tag);
        $tag['tagid'] = $tagid;
        $tag['username'] = $mybb->user['username'];
        $template = TagsPlus::generate_tag_row($tag);

        return TagsPlus::json_response(200, $template);
    }

    // If we're not creating a tag, we need to make sure the tagid exists
    if (!isset($mybb->input['tagid'])) {
        return TagsPlus::json_response(403, "Tag id field is not set");
    }
    $tagid = $mybb->input['tagid'];
    $tag = TagsPlus::get_tag_by_id($tagid);
    if ($tag == null) {
        return TagsPlus::json_response(403, "Tag does not exist");
    }

    /**
     * Delete a tag
     * POST: xmlhttp.php?action=tagsplus&do=delete
     */
    if ($mybb->get_input('do') === 'delete') {
        // delete the tag and associations
        $db->delete_query('posttags', 'tagid='.$db->escape_string($tagid));
        $db->delete_query('tags', 'tagid='.$db->escape_string($tagid));
        return TagsPlus::json_response(200);
    }
    /**
     * Toggle the enabled/disabled for a tag
     * POST: xmlhttp.php?action=tagsplus&do=toggle
     */
    if ($mybb->get_input('do') === 'toggle') {
        $db->update_query('tags', ['enabled' => ($tag['enabled'] == 1 ? 0 : 1)], 'tagid='.$tag['tagid']);
        return TagsPlus::json_response(200);
    }
    /**
     * Update the name of an existing tag
     * POST: xmlhttp.php?action=tagsplus&do=update-name
     */
    if ($mybb->get_input('do') === 'update-name') {
        if (!isset($mybb->input['name'])) {
            return TagsPlus::json_response(403, "Name field is not set");
        }
        $name = $mybb->input['name'];
        $slug = $mybb->input['slug'];
        if (TagsPlus::get_tag_by_name($name) != null) {
            return TagsPlus::json_response(403, "Tag already exists");
        }
        $db->update_query('tags',
            [
                'name' => $db->escape_string($name),
                'slug' => $db->escape_string($slug)
            ], 'tagid='.$tag['tagid']);
        return TagsPlus::json_response(200);
    }
}