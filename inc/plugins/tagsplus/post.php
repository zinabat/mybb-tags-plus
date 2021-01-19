<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Add the tags input to the new reply area
 */
$plugins->add_hook('newthread_start', 'tagsplus_show_input');
$plugins->add_hook('newreply_start', 'tagsplus_show_input');
$plugins->add_hook('editpost_end', 'tagsplus_show_input');
function tagsplus_show_input() {
    global $mybb, $templates, $fid, $forum;
    // template variables
    global $tag_post_input, $tags_value, $tagsscript;
    $fid = $fid ? $fid : $forum['fid'];
    if (!TagsPlus::allowed_forum($fid)) {
        return;
    }
    $tags = TagsPlus::get_all_tags();
    $tags_editable = ' editable';
    // create a template for the badge for our javascript
    $tag = [
        'name' => 'NAME',
        'tagid' => 'ID'
    ];
    $tags_editable = ' editable';
    $badge_template = '';
    eval('$badge_template = "'.$templates->get('tagsplus_badge').'";');
    // remove html comments
    $badge_template = preg_replace("/<!--(?!<!)[^\[>].*?-->/", "", $badge_template);

    $tagsscript = "<script type=\"module\">
        import { tags } from '{$mybb->settings['bburl']}/inc/plugins/tagsplus/tagsplus-post.js';
        const badgeTemplateContainer = document.createElement('div');
        badgeTemplateContainer.innerHTML = `{$badge_template}`;
        tags(".json_encode($tags).", badgeTemplateContainer.firstElementChild);
    </script>";
    // todo: allow users to make their own tags is setting enabled
    if (empty($tags)) return;

    $tags_value = '';
    if ($mybb->input['action'] == 'editpost') {
        $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
        $current_tags = TagsPlus::get_post_tags($pid, true);
        $tag_badges = TagsPlus::generate_badges($current_tags, true);
        $tags_to_ids = function($tag) {
            return $tag['tagid'];
        };
        $tags_value = implode(',',array_map($tags_to_ids, $current_tags));
    }
    eval('$tag_post_input = "'.$templates->get('tagsplus_input').'";');
}

/**
 * Validate tags when posts are validated
 */
$plugins->add_hook('datahandler_post_validate_post', 'tagsplus_validate_post');
function tagsplus_validate_post(&$datahandler) {
    global $lang;
    $lang->load('tagsplus');
    $tagids = TagsPlus::get_input_tags();
    if (empty($tagids)) return;
    // verify they are all integers
    foreach ($tagids as $tagid) {
        if (!is_numeric($tagid)) {
            $datahandler->set_error("tags_not_found");
            return;
        }
    }

    if (!TagsPlus::tags_exist($tagids)) {
        $datahandler->set_error("tags_not_found");
    }

}

/**
 * Insert tags when posts are inserted
 */
$plugins->add_hook('datahandler_post_insert_post_end', 'tagsplus_attach_tags');
$plugins->add_hook('datahandler_post_insert_thread_end', 'tagsplus_attach_tags');
function tagsplus_attach_tags(&$datahandler) {
    global $db;
    $tagids = TagsPlus::get_input_tags();
    $queries = [];
    // insert multiple into the posttags table
    foreach ($tagids as $tagid) {
        $queries[] = [
            'tagid' => $tagid,
            'pid' => $datahandler->pid,
            'dateline' => TIME_NOW
        ];
    }
    $db->insert_query_multiple('posttags', $queries);
}

/**
 * Update tags when posts are updated
 */
$plugins->add_hook('datahandler_post_update', 'tagsplus_update_tags');
function tagsplus_update_tags(&$datahandler) {
    global $db;

    $current_tagids = TagsPlus::get_post_tags($datahandler->pid);
    $new_tagids = TagsPlus::get_input_tags();

    // remove any that are missing from the input
     // todo: log the removal somewhere
    $remove_ids = array_diff($current_tagids, $new_tagids);
    if (count($remove_ids)){
        $db->delete_query('posttags', 'pid='.$datahandler->pid.' AND tagid IN ('.(implode(',', $remove_ids)).')');
    }

    // add any that are new
    $add_ids = array_diff($new_tagids, $current_tagids);
    if (count($add_ids)) {
        $insert_queries = [];
        foreach ($add_ids as $tagid) {
            $insert_queries[] = [
                'tagid' => $tagid,
                'pid' => $datahandler->pid,
                'dateline' => TIME_NOW
            ];
        }
        $db->insert_query_multiple('posttags', $insert_queries);
    }
}

/**
 * Remove all associated tags from database when a post is deleted
 */
$plugins->add_hook('class_moderation_delete_post_start', 'tagsplus_delete_post');
function tagsplus_delete_post(&$pid) {
    global $db;
    $db->delete_query('posttags', 'pid='.$pid);
}
/**
 * Remove all associated tags from database when a thread is deleted
 */
$plugins->add_hook('class_moderation_delete_thread_start', 'tagsplus_delete_thread');
function tagsplus_delete_thread(&$tid) {
    global $db;
    $db->write_query("DELETE FROM `{$db->table_prefix}posttags` WHERE pid in
        (SELECT pid from `{$db->table_prefix}posts` WHERE tid={$tid})");
}