<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook('showthread_start', 'tagsplus_showthread');
function tagsplus_showthread()
{
    global $mybb, $db, $ismod, $fid, $forumpermissions, $thread, $templates;
    global $tag_badges;
    $tid = $thread['tid'];
    $thread_tags = TagsPlus::get_thread_tags([$tid])[$tid];
    $thread['tag_badges'] = TagsPlus::generate_badges($thread_tags);
    // STOLEN FROM showthread.php
    if ($ismod && is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == false) {
        $visible = "AND p.visible IN (-1,1)";
    } elseif ($ismod && is_moderator($fid, "canviewdeleted") == false && is_moderator($fid, "canviewunapprove") == true) {
        $visible = "AND p.visible IN (0,1)";
    } elseif ($ismod && is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == true) {
        $visible = "AND p.visible IN (-1,0,1)";
    } elseif ($forumpermissions['canviewdeletionnotice'] != 0 && $ismod == false) {
        $visible = "AND p.visible IN (-1,1)";
    } else {
        $visible = "AND p.visible='1'";
    }
    if ($mybb->user['uid'] && $mybb->settings['showownunapproved']) {
        $visible .= " OR (p.tid='$tid' AND p.visible='0' AND p.uid=" . $mybb->user['uid'] . ")";
    }
    $postcount = (int)$thread['replies'] + 1;
    $perpage = $mybb->settings['postsperpage'];
    $pages = $postcount / $perpage;
    $pages = ceil($pages);
    if ($mybb->get_input('page') == "last") {
        $page = $pages;
    }

    if ($page > $pages || $page <= 0) {
        $page = 1;
    }
    if ($page) {
        $start = ($page - 1) * $perpage;
    } else {
        $start = 0;
        $page = 1;
    }
    $query = $db->simple_select("posts p", "GROUP_CONCAT(p.pid) as pids", "p.tid='$tid' $visible", array('order_by' => 'p.dateline', 'limit_start' => $start, 'limit' => $perpage));
    // END THIEVERY
    $pids = explode(',', $db->fetch_field($query, 'pids'));
    $tags_by_pid = TagsPlus::get_thread_post_tagids($pids);
    $tag_badges = [];
    // generate a global to show the badges for each post
    foreach ($tags_by_pid as $pid=>$post_tags) {
        $tag_badges[$pid] = TagsPlus::generate_badges($post_tags);
    }
}
/**
 * Display the badges for each post
 */
$plugins->add_hook('postbit', 'tagsplus_postbit');
function tagsplus_postbit(&$post)
{
    global $tag_badges;
    $post['tag_badges'] = $tag_badges[$post['pid']];
}

/**
 * Display badges for each thread when viewing the forum
 */
$plugins->add_hook('forumdisplay_before_thread', 'tagsplus_before_thread');
function tagsplus_before_thread(&$args)
{
    global $fid;
    if (!TagsPlus::allowed_forum($fid) || !count($args['tids'])) return;

    $tags_by_tid = TagsPlus::get_thread_tags($args['tids']);
    foreach ($tags_by_tid as $tid=>$thread_tags) {
        $args['threadcache'][$tid]['tag_badges'] = TagsPlus::generate_badges($thread_tags);
    }
}

$plugins->add_hook('forumdisplay_end', 'tagsplus_display_filters');
function tagsplus_display_filters()
{
    global $fid;
    global $tagsplus_filters;
    if (!TagsPlus::allowed_forum($fid)) return;

    $forum_tags = TagsPlus::get_forum_tags($fid);
    $tagsplus_filters = TagsPlus::generate_tag_filters($forum_tags, $fid);
}

$plugins->add_hook('forumdisplay_get_threads', 'tagsplus_filter_by_tag');
function tagsplus_filter_by_tag()
{
    global $mybb, $db;
    global $tuseronly; // we're hijacking this to add shit to the query
    // todo: add support for multiple and negation
    $tagid = $mybb->get_input('tags', MyBB::INPUT_INT);
    if (!$tagid || !TagsPlus::tags_exist([$tagid])) return;

    // select only the thread ids that have this tag
    $tuseronly .= " AND t.tid IN (SELECT distinct tid from `{$db->table_prefix}posttags` as pt
    left join `{$db->table_prefix}posts` as p on pt.pid=p.pid
    where pt.tagid={$tagid})";
}