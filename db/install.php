<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_student_progress_install() {
    global $DB;

    // Agregar el bloque a todos los cursos existentes
    $courses = $DB->get_records('course');

    foreach ($courses as $course) {
        $page = new moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');

        blocks_add_default_course_blocks($course);
        blocks_add_block($page, 'student_progress', BLOCK_POS_RIGHT, 0, false);
    }
}
