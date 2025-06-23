<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_studentprogressblock_install() {
    global $DB;

    $courses = $DB->get_records('course', array());
    foreach ($courses as $course) {
        $page = new moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');
        $page->set_pagetype('course-view-*');
        $page->set_url('/course/view.php', array('id' => $course->id));
        blocks_add_block('studentprogressblock', $page, 'side-post', 0, false);
    }
}
