<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

define('PLUGIN_TAGSPLUS_ROOT', MYBB_ROOT . 'inc/plugins/tagsplus');

require_once(PLUGIN_TAGSPLUS_ROOT . '/TagsPlus.php');

//$lang->load('tagsplus');

function tagsplus_info()
{
    return [
        "name"          => "Tags Plus",
        "description"   => "Tags for threads and posts",
        "website"       => "https://github.com/zinabat/mybb-tags-plus",
        "author"        => "Zina Ramirez",
        "authorsite"    => "https://www.linkedin.com/in/zina-ramirez-7125bb90/",
        "version"       => "0.0",
        "guid"          => "",
        "codename"      => "tagsplus",
        "compatibility" => "18*"
    ];
}

function tagsplus_install()
{
    global $db;
    require_once(MYBB_ROOT . "admin/inc/functions_themes.php");
    require_once(PLUGIN_TAGSPLUS_ROOT . "/templates.php");
    // add tables we'll need
    $db->write_query("CREATE TABLE `{$db->table_prefix}tags` (
        tagid INT(10) UNSIGNED NOT NULL auto_increment,
        uid INT(10) UNSIGNED NOT NULL DEFAULT 0,
        slug VARCHAR(64),
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        dateline INT UNSIGNED NOT NULL DEFAULT 0,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY(tagid)
    ) engine=MyISAM");
    $db->write_query("CREATE TABLE `{$db->table_prefix}posttags` (
        ptid INT(10) UNSIGNED NOT NULL auto_increment,
        tagid INT(10) UNSIGNED NOT NULL,
        pid INT(10) UNSIGNED NOT NULL,
        dateline INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(ptid)
    ) engine=MyISAM");
    $db->write_query("ALTER TABLE `{$db->table_prefix}usergroups`
        ADD `canmanagetags` TINYINT( 1 ) NOT NULL DEFAULT '0'");
    // allow tag management for admins, superadmins, and mods
    $db->update_query('usergroups', ['canmanagetags' => '1'], 'gid IN (4,3,6)');

    // add templates
    $db->insert_query('templategroups', [
        'prefix' => 'tagsplus',
        'title'  => 'Tags Plus',
        'isdefault' => 1
    ]);
    $template_queries = [];
    foreach ($templates as $name => $template) {
        $template_queries[] = [
            'title' => 'tagsplus_'.$name,
            'template' => $db->escape_string($template),
            'sid' => '-2',
            'version' => '1824',
            'status' => '',
            'dateline' => TIME_NOW
        ];
    }
    $db->insert_query_multiple('templates', $template_queries);

    // Add stylesheet to the master template so it becomes inherited.
    $stylesheet = @file_get_contents(PLUGIN_TAGSPLUS_ROOT . '/tags-plus.css');
    $db->insert_query('themestylesheets', [
        'sid' => NULL,
        'name' => 'tags-plus.css',
        'tid' => '1',
        'stylesheet' => $db->escape_string($stylesheet),
        'cachefile' => 'tags-plus.css',
        'lastmodified' => TIME_NOW
    ]);
    cache_stylesheet(1, "tags-plus.css", $stylesheet);
    update_theme_stylesheet_list("1");
    // add settings
    $gid = $db->insert_query("settinggroups", [
        'name' => 'tagsplus_settings',
        'title' => 'Tags Plus Settings',
        'description' => 'Settings for adding tags to threads',
        'disporder' => 5, // The order your setting group will display
        'isdefault' => 0
    ]);
    $settings = [
        [
            'name' => 'tagsplus_maxlength',
            'title' => 'Maximum length',
            'description' => 'Enter maximum character length for a tag. Must be less than 256.',
            'optionscode' => 'numeric',
            'value' => 255,
            'disporder' => 1,
            "gid" => $gid
        ], [
            'name' => 'tagsplus_minlength',
            'title' => 'Minimum length',
            'description' => 'Enter minimum character length for a tag. Must be more than 0.',
            'optionscode' => 'numeric',
            'value' => 2,
            'disporder' => 2,
            "gid" => $gid
        // ], [
        //     'name' => 'tagsplus_preapproved_only',
        //     'title' => 'Preapproved tags only',
        //     'description' => 'Select whether available tags should be created by a moderator only.',
        //     'optionscode' => 'onoff',
        //     'value' => 1,
        //     'disporder' => 3,
        //     "gid" => $gid
        ], [
            'name' => 'tagsplus_disable_forums',
            'title' => 'Disable forums',
            'description' => 'Select forums where tags should be disabled.',
            'optionscode' => 'forumselect',
            'value' => 0,
            'disporder' => 4,
            "gid" => $gid
        ]
    ];
    $db->insert_query_multiple("settings", $settings);

    rebuild_settings();
}

/**
 * @return boolean
 */
function tagsplus_is_installed()
{
    global $db;
    if ($db->table_exists("tags") && $db->table_exists("posttags")) {
        return true;
    }
    return false;
}

function tagsplus_uninstall()
{
    global $db;
    require_once(MYBB_ROOT . "admin/inc/functions_themes.php");
    // Remove stylesheets from the theme cache directories if it exists
    $query = $db->simple_select("themes", "tid");
    while ($tid = $db->fetch_field($query, "tid")) {
        $css_file = MYBB_ROOT . "cache/themes/theme{$tid}/tags-plus.css";
        if (file_exists($css_file))
            unlink($css_file);
    }
    $db->delete_query('themestylesheets', 'tid=1 AND name="tags-plus.css"');
    update_theme_stylesheet_list("1");
    // remove the tables we added
    $db->write_query("ALTER TABLE `{$db->table_prefix}usergroups` DROP `canmanagetags`");
    $db->drop_table('posttags');
    $db->drop_table('tags');
    // remove templates
    $db->delete_query("templates", "title LIKE 'tagsplus_%'");
    // remove settings
    $query = $db->simple_select('settinggroups', 'gid', 'name="tagsplus_settings"');
    $gid = $db->fetch_field($query, 'gid');
    $db->delete_query('settings', 'gid=' . $gid);
    $db->delete_query('settinggroups', 'gid=' . $gid);
}

function tagsplus_activate()
{
    require_once(MYBB_ROOT.'inc/adminfunctions_templates.php');
    // add the script to the footer
    find_replace_templatesets('newthread', '#'.preg_quote('{$footer}').'#', '{$footer}{$tagsscript}');
    find_replace_templatesets('newreply', '#'.preg_quote('{$footer}').'#', '{$footer}{$tagsscript}');
    find_replace_templatesets('editpost', '#'.preg_quote('{$footer}').'#', '{$footer}{$tagsscript}');
    // add the templates to the existing ones
    find_replace_templatesets('newthread', '#'.preg_quote('{$posticons}').'#', '{$posticons}{$tag_post_input}');
    find_replace_templatesets('newreply', '#'.preg_quote('{$posticons}').'#', '{$posticons}{$tag_post_input}');
    find_replace_templatesets('editpost', '#'.preg_quote('{$posticons}').'#', '{$posticons}{$tag_post_input}');
    find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'icon\']}').'#', '{$post[\'icon\']}{$post[\'tag_badges\']}');
    find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'multipage\']}').'#', '{$thread[\'multipage\']}{$thread[\'tag_badges\']}');
    find_replace_templatesets('forumdisplay', '#'.preg_quote('{$subforums}').'#', '{$subforums}{$tagsplus_filters}');
    // add modcp templates
    find_replace_templatesets('modcp_nav_forums_posts', '#'.preg_quote('{$nav_modqueue}').'#', '{$nav_modqueue}{$nav_edittags}');
}

function tagsplus_deactivate()
{
    require_once(MYBB_ROOT.'inc/adminfunctions_templates.php');
    // remove the script from the footer
    find_replace_templatesets('newthread', '#'.preg_quote('{$tagsscript}').'#', '');
    find_replace_templatesets('newreply', '#'.preg_quote('{$tagsscript}').'#', '');
    find_replace_templatesets('editpost', '#'.preg_quote('{$tagsscript}').'#', '');
    // remove templates
    find_replace_templatesets('newthread', '#'.preg_quote('{$tag_post_input}').'#', '');
    find_replace_templatesets('newreply', '#'.preg_quote('{$tag_post_input}').'#', '');
    find_replace_templatesets('editpost', '#'.preg_quote('{$tag_post_input}').'#', '');
    find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'tag_badges\']}').'#', '');
    find_replace_templatesets('forumdisplay_thread', '#'.preg_quote('{$thread[\'tag_badges\']}').'#', '');
    find_replace_templatesets('forumdisplay', '#'.preg_quote('{$tagsplus_filters}').'#', '');
    // remove modcp templates
    find_replace_templatesets('modcp_nav_forums_posts', '#'.preg_quote('{$nav_edittags}').'#', '');
}

require_once(PLUGIN_TAGSPLUS_ROOT.'/forum.php');
require_once(PLUGIN_TAGSPLUS_ROOT.'/modcp.php');
require_once(PLUGIN_TAGSPLUS_ROOT.'/post.php');