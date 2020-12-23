<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function tagsplus_info() {
    return [
        "name"          => "Tags Plus",
        "description"   => "Moderator-defined tags for threads",
        "website"       => "https://www.linkedin.com/in/zina-ramirez-7125bb90/",
        "author"        => "Zina Ramirez",
        "authorsite"    => "https://www.linkedin.com/in/zina-ramirez-7125bb90/",
        "version"       => "0.0",
        "guid"          => "",
        "codename"      => "tagsplus",
        "compatibility" => "18*"
	];
}

function tagsplus_install() {

}

/**
 * @return boolean
 */
function tagsplus_is_installed() {
	global $db;
    if ($db->table_exists("thread_tags")) {
        return true;
    }
    return false;
}

function tagsplus_uninstall() {

}

function tagsplus_activate() {

}

function tagsplus_deactivate() {

}