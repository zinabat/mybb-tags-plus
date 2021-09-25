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
    global $tag_post_input, $tags_value, $tagsscript, $pid;
    $fid = $fid ? $fid : $forum['fid'];
    if (!TagsPlus::allowed_forum($fid)) {
        return;
    }
    $tags = TagsPlus::get_all_tags();

    // todo: allow users to make their own tags is setting enabled
    if (empty($tags)) return;

    $tags_value = '';
    $current_tags = [];
    if ($mybb->input['action'] == 'editpost' || $mybb->input['action'] == 'editdraft' || $mybb->get_input('thread-tag-ids')) {
        $pid = $pid ? $pid : $mybb->get_input('pid', MyBB::INPUT_INT);

        if ($pid) {
            $current_tags = TagsPlus::get_post_tags($pid, true);
            $tags_to_ids = function($tag) {
                return $tag['tagid'];
            };
            $tags_value = implode(',',array_map($tags_to_ids, $current_tags));
        } else {
            $tagids = explode(',', $mybb->get_input('thread-tag-ids'));
            $current_tags = [];
            foreach ($tagids as $tagid) {
                $current_tags[] = $tags[$tagid];
            }
            $tags_value = $mybb->get_input('thread-tag-ids');
        }
    }
    $tagsscript = "<script src='{$mybb->settings['bburl']}/inc/plugins/tagsplus/d2l-attribute-picker.js?v=1.0.3'></script>
    <script type=\"module\">
        import { tags } from '{$mybb->settings['bburl']}/inc/plugins/tagsplus/tagsplus-post.js?v=1.2';
        const assignedTags = ".json_encode($current_tags).".map(tag => tag.name);
        tags(Object.values(".json_encode($tags)."), assignedTags);
    </script>";

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
    // check if this post already has tags
    // in this case, we're posting from a draft
    $query = $db->simple_select('posttags', 'tagid', 'pid='.$datahandler->pid, ['limit' => 1]);
    if ($db->num_rows($query)) {
        TagsPlus::update_tags($datahandler->pid);
    } else {
        // insert multiple into the posttags table
        $tagids = TagsPlus::get_input_tags();
        if (empty($tagids)) return;
        $queries = [];
        foreach ($tagids as $tagid) {
            $queries[] = [
                'tagid' => $tagid,
                'pid' => $datahandler->pid,
                'dateline' => TIME_NOW
            ];
        }
        $db->insert_query_multiple('posttags', $queries);
    }
}

/**
 * Update tags when posts are updated
 */
$plugins->add_hook('datahandler_post_update', 'tagsplus_update_tags');
function tagsplus_update_tags(&$datahandler) {
    global $db, $mybb;

    // don't make this a thing for ajax
    if (defined('THIS_SCRIPT') && THIS_SCRIPT == 'xmlhttp.php') return;

    TagsPlus::update_tags($datahandler->pid);
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