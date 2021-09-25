<?php

class TagsPlus {

    /**
     * Respond with json
     */
    public static function json_response($status = 200, $data = '') {
        global $charset;
        http_response_code($status);
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($data);
        exit;
    }

    /**
     * Fetches a tag from the database by name
     * @return Array The tag if found, null otherwise
     */
    public static function get_tag_by_name($name) {
        global $db;
        $query = $db->simple_select('tags', '*', 'name LIKE "'.$db->escape_string($name).'"');
        if (!$db->num_rows($query)) {
            return null;
        }
        return $db->fetch_array($query);
    }
    /**
     * Fetches a tag from the database by id
     * @return Array The tag if found, null otherwise
     */
    public static function get_tag_by_id($tagid) {
        global $db;
        $query = $db->simple_select('tags', '*', 'tagid='.$db->escape_string($tagid));
        if (!$db->num_rows($query)) {
            return null;
        }
        return $db->fetch_array($query);
    }

    public static function tags_exist($tagids) {
        global $db;
        $query = $db->simple_select('tags', 'tagid', 'tagid IN ('.implode(',', $tagids).')');
        if ($db->num_rows($query) !== count($tagids)) return false;

        return true;
    }

    public static function update_tags($pid) {
        global $db;
        $current_tagids = self::get_post_tags($pid);
        $new_tagids = self::get_input_tags();

        // remove any that are missing from the input
        // todo: log the removal somewhere
        $remove_ids = array_diff($current_tagids, $new_tagids);
        if (count($remove_ids)){
            $db->delete_query('posttags', 'pid='.$pid.' AND tagid IN ('.(implode(',', $remove_ids)).')');
        }

        // add any that are new
        $add_ids = array_diff($new_tagids, $current_tagids);
        if (count($add_ids)) {
            $insert_queries = [];
            foreach ($add_ids as $tagid) {
                $insert_queries[] = [
                    'tagid' => $tagid,
                    'pid' => $pid,
                    'dateline' => TIME_NOW
                ];
            }
            $db->insert_query_multiple('posttags', $insert_queries);
        }
    }

    public static function get_all_tags($enabled = 1) {
        global $db;
        $query = $db->simple_select('tags', 'tagid, name, slug', 'enabled='.$enabled);
        $tags = [];
        while ($tag = $db->fetch_array($query)) {
            $tags[$tag['tagid']] = $tag;
        }
        return $tags;
    }

    // todo: support multiple tags for the tag link
    // todo: make it possible to have tags be specific to certain forums
    public static function get_forum_tags($fid) {
        global $db, $mybb, $string;
        if (!$string) {
            if ($mybb->seo_support == true) {
                $string = "?";
            } else {
                $string = "&amp;";
            }
        }
        $mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);

        $query = $db->write_query("SELECT distinct tag.tagid,tag.slug,tag.name FROM `{$db->table_prefix}posttags` as pt
            left join `{$db->table_prefix}tags` as tag on tag.tagid=pt.tagid
            left join `{$db->table_prefix}posts` as p on p.pid=pt.pid
            where p.fid={$fid}");
        $tags = [];
        while ($tag = $db->fetch_array($query)) {
            if ($mybb->input['page'] > 1) {
                $tag['link'] = get_forum_link($fid, $mybb->input['page']).$string."tags={$tag['tagid']}";
            } else {
                $tag['link'] = get_forum_link($fid).$string."tags={$tag['tagid']}";
            }
            $tags[] = $tag;
        }
        return $tags;
    }

    public static function get_post_tags($pid, $all_info = false) {
        global $db;
        if (!$all_info) {
            $query = $db->simple_select('posttags', 'GROUP_CONCAT(tagid SEPARATOR ",") as tagids', 'pid = '.$pid, [
                'group_by' => 'pid'
            ]);
            if (!$db->num_rows($query)) return [];
            return explode(',', $db->fetch_field($query, 'tagids'));
        }
        // get all the post's tags with their information
        $query = $db->write_query("SELECT t.tagid,t.name,t.slug FROM `{$db->table_prefix}posttags` as pt
            LEFT JOIN `{$db->table_prefix}tags` as t ON t.tagid=pt.tagid
            WHERE pt.pid={$pid}");
        $tags = [];
        while ($tag = $db->fetch_array($query)) {
            $tags[] = $tag;
        }
        return $tags;
    }

    // todo: implement caching for this
    /**
     * Gathers tags for each thread given a set of tids
     * @param Array List of thread ids
     * @return Array A 2-D array of tags keyed by the thread id
     */
    public static function get_thread_tags($tids) {
        global $db;
        $tids_string = implode(',', $tids);
        $query = $db->write_query("SELECT distinct t.tid, tag.tagid,tag.slug,tag.name FROM `{$db->table_prefix}posttags` as pt
            left join `{$db->table_prefix}tags` as tag on tag.tagid=pt.tagid
            left join `{$db->table_prefix}posts` as p on p.pid=pt.pid
            left join `{$db->table_prefix}threads` as t on t.tid=p.tid
            where t.tid in ({$tids_string})");
        $tags_by_tid = [];
        while ($tag = $db->fetch_array($query)) {
            if (array_key_exists($tag['tid'], $tags_by_tid)) {
                $tags_by_tid[$tag['tid']][] = ['tagid' => $tag['tagid'], 'name' => $tag['name']];
            } else {
                $tags_by_tid[$tag['tid']] = array(['tagid' => $tag['tagid'], 'name' => $tag['name']]);
            }
        }
        return $tags_by_tid;
    }

    /**
     * Gathers the tags for each post given a set of pids
     * @param Array The list of post ids to get tags for
     * @return Array A 2-D array of tags keyed by their post id
     */
    public static function get_thread_post_tagids($pids) {
        global $db;
        $pids_string = implode(',', $pids);
        // pack all the tags for each post into an array of json objects
        $query = $db->write_query("SELECT p.pid, CONCAT('[',GROUP_CONCAT(JSON_OBJECT('tagid',tag.tagid,'name',tag.name,'slug',tag.slug)), ']') as tags FROM `{$db->table_prefix}posttags` as pt
            left join `{$db->table_prefix}tags` as tag on tag.tagid=pt.tagid
            left join `{$db->table_prefix}posts` as p on p.pid=pt.pid
            where p.pid in ({$pids_string})
            group by p.pid");
        $tags_by_pid = [];
        while ($post_row = $db->fetch_array($query)) {
            $tags_by_pid[$post_row['pid']] = json_decode($post_row['tags'], true);
        }
        return $tags_by_pid;
    }

    public static function get_input_tags() {
        global $mybb;
        $tagids = $mybb->get_input('thread-tag-ids');
        if (!$tagids) return [];

        return array_unique(explode(',', $tagids));
    }

    /**
     * Whether the given forum has tags allowed
     * @param int The forum id
     * @return boolean Whether the forum has tags allowed
     */
    public static function allowed_forum($fid)
    {
        global $mybb;
        $forums = $mybb->settings['tags_disallowedforums'];
        if ($forums == -1) { // all disabled
            return false;
        } elseif ($forums == 0 || !$forums) { // none disabled or none set
            return true;
        }
        $forums = explode(',', $forums);
        return in_array($fid, $forums);
    }

    /**
     * Evaluates the tag row from a tag and a count
     * @return String The evaluated row template
     */
    public static function generate_tag_row($tag, $tag_count = 0) {
        global $templates;
        $rowclass = $tag_count % 2 == 0 ? 'trow1' : 'trow2';
        $tag_id = $tag['tagid'];
        $tag_name = $tag['name'];
        $tag_class = $tag['slug'] ? "tagsplus-badge-{$tag['slug']}" : "";
        $tag_slug = $tag['slug'];
        $tag_createdby = $tag['username'];
        $tag_uses = $tag['uses'] ? $tag['uses'] : 0;
        $tag_enable_button = '<button class="tag-toggle-enable" data-tagid="'.$tag['tagid'].'">'.
            ($tag['enabled'] == 0 ? 'Enable' : 'Disable').
        '</button>';
        $tag_delete_button = '<button class="tag-delete" data-tagid="'.$tag['tagid'].'">Delete</button>';
        $tag_actions = $tag_enable_button . $tag_delete_button;
        $tag_row = '';
        eval("\$tag_row =\"".$templates->get("tagsplus_modcp_tags_tagrow")."\";");
        return $tag_row;
    }

    /**
     * @param Array An array of tags with the name and tagid set
     * @return String The evaluated badges template
     */
    public static function generate_badges($tags, $editable=false) {
        global $templates;
        $tag_badges = '';
        if (!$tags) $tags = [];
        if ($editable) $tags_editable = ' editable';
        foreach ($tags as $tag) {
            eval('$tag_badges .= "'.$templates->get('tagsplus_badge').'";');
        }
        return $tag_badges;
    }

    public static function generate_tag_filters($tags, $fid) {
        global $templates, $mybb;
        $tag_filters = '';
        foreach ($tags as $tag) {
            eval('$tag_filters .= "'.$templates->get('tagsplus_filter').'";');
        }
        return $tag_filters;
    }
}
