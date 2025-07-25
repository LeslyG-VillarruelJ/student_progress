<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_student_progress_install() {
    global $DB;

    // Obtener todos los cursos (excluyendo el curso sitio - id = 1)
    $courses = $DB->get_records_select('course', 'id > 1');

    foreach ($courses as $course) {
        // Preparar bloque en la región lateral (side-pre)
        $page = new moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');
        $page->set_url(new moodle_url('/course/view.php', ['id' => $course->id]));
        $page->blocks->add_block('student_progress', 'side-pre', 0, false);
    }
}
