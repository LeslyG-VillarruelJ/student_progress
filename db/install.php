<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_student_progress_install() {
    global $DB;

    // Asegura que el bloque está registrado.
    $blockname = 'student_progress';

    // Obtener todos los cursos excepto el curso sitio (id = 1).
    $courses = $DB->get_records_select('course', 'id != 1');

    foreach ($courses as $course) {
        $page = new moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');
        $page->set_url('/course/view.php', ['id' => $course->id]);

        // Asegura que no esté ya agregado.
        if (!block_is_on_page($blockname, $page)) {
            // Agrega el bloque automáticamente a la región lateral derecha.
            blocks_add_block($blockname, $page, 'side-post', 0, false);
        }
    }
}