<?php

class block_student_progress extends block_base
{

    public function init()
    {
        $this->title = get_string('pluginname', 'block_student_progress');
    }

    public function get_content()
    {
        global $USER, $COURSE, $DB, $PAGE;

        // Si ya hay contenido, retornarlo.
        if ($this->content !== null) {
            return $this->content;
        }

        // Verifica si el usuario tiene rol de profesor o admin en el curso.
        $context = context_course::instance($COURSE->id);
        if (has_capability('moodle/course:update', $context, $USER->id)) {
            // Profesor o administrador: no mostrar el bloque.
            $this->content = new stdClass();
            $this->content->text = '';
            $this->content->footer = '';
            return $this->content;
        }

        $coursename = format_string($COURSE->fullname);
        $courseid = $COURSE->id;
        $userid = $USER->id;

        $PAGE->requires->css(new moodle_url('/blocks/student_progress/styles.css'));

        $userlearning = $this->get_user_learning($userid);
        $userlearningid = $userlearning->id;
        $userlearningname = $userlearning->name;

        $sections = $this->get_user_sections($userid, $courseid);

        list($finishsections, $totalsections) = $this->get_section_progress($userid, $sections, $courseid);

        $progreso = $this->get_progress_resource($userid, $courseid, $sections);

        $message = $this->get_motivational_message($progreso, $courseid);
        $duration = 20000;

        $html = '
        <script>
            function toggleSubtema(id) {
                var subtema = document.getElementById(id);
                var icon = document.getElementById("icon-" + id);
                if (subtema.classList.contains("hidden")) {
                    subtema.classList.remove("hidden");
                    icon.textContent = "▼";
                } else {
                    subtema.classList.add("hidden");
                    icon.textContent = "▶";
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                var mensaje = document.getElementById("mensaje-motivacional");
                if (mensaje) {
                    mensaje.style.display = "block";
                    setTimeout(function() {
                        mensaje.style.display = "none";
                    }, ' . $duration . ');
                }
            });
        </script>
    
        <div class="itinerario-container">
            <div class="itinerario-title">' . $coursename . '</div>
    
            <div class="progreso-general">
                <div style="margin-top: 5px; font-size: 18px; font-weight: bold;">Progreso por recursos</div><br/>
                <div class="progreso-circle" style="background: conic-gradient(#f7b801 0% ' . $progreso . '%, #0f4c5c
                ' . $progreso . '% 100%);">
                    <div class="progreso-text">' . $progreso . '%</div>
                </div>
            </div>

            <div id="mensaje-motivacional" class="mensaje-motivacional" style="display: none;">
                "' . $message . '"
            </div>

            <div style="margin-top: 10px;"><strong>Tipo de Aprendizaje</strong><br>' . $userlearningname . '</div>
    
            <div class="estado-leyenda">
                <strong>Estados</strong><br>
                <span class="estado-item"><span class="dot amarillo"></span>En progreso</span>
                <span class="estado-item"><span class="dot verde"></span>Resuelto</span>
                <span class="estado-item"><span class="dot rojo"></span>Por resolver</span>
            </div>

            <table class="tema-tabla">  
                <tr class="tema-header">
                    <th>Temas</th>
                    <th>Progreso<br/>Temas: ' . $finishsections . '/' . $totalsections . '</th>
                </tr>';

        $idcontador = 1;
        foreach ($sections as $section) {
            $sectionid = $section->section_id ?: 0;

            $resources = $this->get_user_resources($userid, $sectionid);

            $sectionname = $section->section_name ?: "Tema sin nombre";

            $html .= '
                <tr class="tema-row">
                    <td class="tema-col">
                        <span class="toggle" id="icon-sub' . $idcontador . '" onclick="toggleSubtema(\'sub' . $idcontador . '\')">▶</span>
                        ' . $sectionname . '
                        <div class="subtemas hidden" id="sub' . $idcontador . '">';

            if (!$resources) {
                $html .= '<span>Sin recursos<span/>';
            }

            $idcont = 1;
            foreach ($resources as $resource) {
                $resourcename = $resource->nombre_actividad ?: "Recurso sin nombre";
                $moduleid = $resource->id_course_module ?: 0;
                $modulename = $resource->tipo_modulo ?: "";
                $html .= $this->render_activity_link($modulename, $moduleid, $resourcename);
                $idcont++;
                $html .= '<br/>';
            }

            $colorstatus = $this->get_section_color_progress($userid, $sectionid, $courseid);

            $html .= '
                        </div>
                    </td>
                    <td class="tema-col dot-col"><span class="' . $colorstatus . '"></span></td>
                </tr>';
            $idcontador++;
        }
        $html .= '</table>
        </div>';

        $this->content = new stdClass();
        $this->content->text = $html;
        $this->content->footer = '';
        return $this->content;
    }

    // Get the learning type of the user
    private function get_user_learning($userid)
    {
        global $DB;

        try {
            $sql = "SELECT lt.name, lt.id
                    FROM {learning_type_plg} lt
                    JOIN {user_learning_plg} ul ON lt.id = ul.id_learning
                    JOIN {user} u ON ul.id_user = u.id
                    WHERE u.id = :userid";

            $params = ['userid' => $userid];
            $learning = $DB->get_record_sql($sql, $params);

            return $learning;
        } catch (Exception $e) {
            debugging('Error en get_user_learning(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    // Get the sections for the user
    private function get_user_sections($userid, $courseid)
    {
        global $DB;

        try {
            $sql = "SELECT DISTINCT s.id AS section_id, s.name AS section_name
                        FROM {user_learning_module_plg} ulm 
                        JOIN {course_modules} cm ON cm.id = ulm.id_learning_course_module
                        JOIN {course_sections} s ON cm.section = s.id
                        JOIN {user} u ON ulm.id_user = u.id
                        WHERE u.id = :userid AND cm.course = :courseid
                    ";

            $params = ['userid' => $userid, 'courseid' => $courseid];

            $sections = $DB->get_records_sql($sql, $params);

            return $sections;
        } catch (Exception $e) {
            debugging('Error en get_user_sections(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    private function get_section_status($userid, $sectionid, $courseid)
    {
        global $DB;

        try {
            $sql1 = "SELECT 
                        (SELECT COUNT(*)
                             FROM {course_modules} cm
                             JOIN {user_learning_module_plg} ulcm ON cm.id = ulcm.id_learning_course_module
                             JOIN {user} u ON ulcm.id_user = u.id
                             WHERE ulcm.id_user = :userid1 AND cm.course = :courseid1 AND cm.section = :sectionid1
                        ) AS total_asignados,

                        (SELECT COUNT(*)
                             FROM {course_modules_completion} cmc
                             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                             JOIN {user_learning_module_plg} ulcm ON cmc.coursemoduleid = ulcm.id_learning_course_module
                             JOIN {user} u ON ulcm.id_user = u.id
                             WHERE u.id = :userid2 AND cmc.userid = :userid3 AND cm.course = :courseid2 AND cmc.completionstate = 1 AND cm.section = :sectionid2
                        ) AS total_completados;;
                    ";

            $params1 = [
                'userid1' => $userid,
                'courseid1' => $courseid,
                'userid2' => $userid,
                'courseid2' => $courseid,
                'sectionid1' => $sectionid,
                'userid3' => $userid,
                'sectionid2' => $sectionid
            ];

            debugging('Antes de la consulta', DEBUG_DEVELOPER);

            $sectionprogress = $DB->get_record_sql($sql1, $params1);

            $finishresources = isset($sectionprogress->total_completados) ? (int)$sectionprogress->total_completados : 0;
            $totalresources = isset($sectionprogress->total_asignados) ? (int)$sectionprogress->total_asignados : 0;

            return [$finishresources, $totalresources];
        } catch (Exception $e) {
            debugging('Error en get_user_sections(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    // Get percentage of progress
    private function get_progress_resource($userid, $courseid, $sections)
    {
        try {
            $addtotalresources = 0;
            $addfinishresources = 0;

            foreach ($sections as $section) {
                $sectionid = $section->section_id ?: 0;
                list($finishresources, $totalresources) = $this->get_section_status($userid, $sectionid, $courseid);
                $addtotalresources += $totalresources;
                $addfinishresources += $finishresources;
            }

            return ($addtotalresources > 0) ? round(($addfinishresources * 100) / $addtotalresources) : 0;;
        } catch (Exception $e) {
            debugging('Error en get_progress_resource(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    // Get finished task
    private function get_section_progress($userid, $sections, $courseid)
    {
        try {
            $finishsections = 0;
            $totalsections = 0;
            foreach ($sections as $section) {
                $sectionid = $section->section_id ?: 0;
                list($finishresources, $totalresources) = $this->get_section_status($userid, $sectionid, $courseid);

                if ($totalresources === $finishresources) {
                    $finishsections += 1;
                }

                $totalsections += 1;
            }

            return [$finishsections, $totalsections];
        } catch (Exception $e) {
            debugging('Error en get_progress_data(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [0, 0];
        }
    }

    private function get_section_color_progress($userid, $sectionid, $courseid)
    {
        $colorstatus = "dot rojo";

        try {
            list($finishresources, $totalresources) = $this->get_section_status($userid, $sectionid, $courseid);

            if ($totalresources === $finishresources) {
                $colorstatus = "dot verde";
            } elseif ($totalresources > $finishresources && $finishresources >= 1) {
                $colorstatus = "dot amarillo";
            } else {
                $colorstatus = "dot rojo";
            }
        } catch (Exception $e) {
            debugging('Error en get_progress_data(): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $colorstatus;
    }


    // Get the resources for the user
    private function get_user_resources($userid, $sectionid)
    {
        global $DB;

        try {
            $sql = "SELECT
                        cm.id AS id_course_module,
                        m.name AS tipo_modulo,
                        CASE m.name
                            WHEN 'assign' THEN a.name
                            WHEN 'book' THEN b.name
                            WHEN 'chat' THEN ch.name
                            WHEN 'choice' THEN chs.name
                            WHEN 'data' THEN d.name
                            WHEN 'feedback' THEN f.name
                            WHEN 'folder' THEN fo.name
                            WHEN 'forum' THEN fm.name
                            WHEN 'glossary' THEN g.name
                            WHEN 'h5pactivity' THEN h.name
                            WHEN 'imscp' THEN i.name
                            WHEN 'label' THEN l.name
                            WHEN 'lesson' THEN le.name
                            WHEN 'page' THEN p.name
                            WHEN 'quiz' THEN q.name
                            WHEN 'resource' THEN r.name
                            WHEN 'scorm' THEN s.name
                            WHEN 'survey' THEN sv.name
                            WHEN 'url' THEN u.name
                            WHEN 'wiki' THEN w.name
                            WHEN 'workshop' THEN wk.name
                            ELSE 'Desconocido'
                        END AS nombre_actividad
                        FROM {course_modules} cm
                        JOIN {course_sections} cs ON cm.section = cs.id
                        JOIN {user_learning_module_plg} ulcm ON cm.id = ulcm.id_learning_course_module
                        JOIN {user} us ON ulcm.id_user = us.id
                        JOIN {modules} m ON cm.module = m.id
                        LEFT JOIN {assign} a ON a.id = cm.instance AND m.name = 'assign'
                        LEFT JOIN {book} b ON b.id = cm.instance AND m.name = 'book'
                        LEFT JOIN {chat} ch ON ch.id = cm.instance AND m.name = 'chat'
                        LEFT JOIN {choice} chs ON chs.id = cm.instance AND m.name = 'choice'
                        LEFT JOIN {data} d ON d.id = cm.instance AND m.name = 'data'
                        LEFT JOIN {feedback} f ON f.id = cm.instance AND m.name = 'feedback'
                        LEFT JOIN {folder} fo ON fo.id = cm.instance AND m.name = 'folder'
                        LEFT JOIN {forum} fm ON fm.id = cm.instance AND m.name = 'forum'
                        LEFT JOIN {glossary} g ON g.id = cm.instance AND m.name = 'glossary'
                        LEFT JOIN {h5pactivity} h ON h.id = cm.instance AND m.name = 'h5pactivity'
                        LEFT JOIN {imscp} i ON i.id = cm.instance AND m.name = 'imscp'
                        LEFT JOIN {label} l ON l.id = cm.instance AND m.name = 'label'
                        LEFT JOIN {lesson} le ON le.id = cm.instance AND m.name = 'lesson'
                        LEFT JOIN {page} p ON p.id = cm.instance AND m.name = 'page'
                        LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
                        LEFT JOIN {resource} r ON r.id = cm.instance AND m.name = 'resource'
                        LEFT JOIN {scorm} s ON s.id = cm.instance AND m.name = 'scorm'
                        LEFT JOIN {survey} sv ON sv.id = cm.instance AND m.name = 'survey'
                        LEFT JOIN {url} u ON u.id = cm.instance AND m.name = 'url'
                        LEFT JOIN {wiki} w ON w.id = cm.instance AND m.name = 'wiki'
                        LEFT JOIN {workshop} wk ON wk.id = cm.instance AND m.name = 'workshop'
                        WHERE us.id = :userid AND cs.id = :sectionid
                    ";

            $params = ['userid' => $userid, 'sectionid' => $sectionid];
            return $DB->get_records_sql($sql, $params);
        } catch (Exception $e) {
            debugging('Error en get_user_resources(): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    private function get_motivational_message($progress, $courseid)
    {
        global $DB;

        try {
            $sql = "SELECT ca.alert FROM {course_alerts_plg} ca WHERE ca.id_course = :courseid";
            $params = ['courseid' => $courseid];
            $messages = $DB->get_records_sql($sql, $params);

            if (!empty($messages)) {
                $idcontador = 1;
                foreach ($messages as $alert) {
                    $percentage = 100 / 4;
                    if ($progress < $percentage * $idcontador) {
                        return $alert->alert;
                    }
                    $idcontador++;
                }
            }
        } catch (Exception $e) {
            debugging('Error en get_motivational_message(): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Mensajes por defecto
        if ($progress < 33.34) return '¡Vamos! Recién comienzas. Cada paso cuenta.';
        if ($progress < 66.67) return '¡Buen inicio! Sigue avanzando paso a paso.';
        if ($progress < 100) return '¡Vas por buen camino! Ya completaste la mitad.';
        return '¡Excelente trabajo! Ya casi terminas.';
    }


    /**
     * Genera un enlace HTML hacia una actividad o recurso de Moodle.
     *
     * @param string $modulename Nombre del módulo (por ejemplo: 'resource', 'quiz', 'assign').
     * @param int $cmid ID del course_module (cm.id).
     * @param string $resourcename Nombre visible del recurso o actividad.
     * @return string HTML del enlace.
     */
    private function render_activity_link(string $modulename, int $cmid, string $resourcename): string
    {
        $url = new moodle_url("/mod/{$modulename}/view.php", ['id' => $cmid]);
        $context = context_module::instance($cmid);
        $formattedname = format_string($resourcename, true, ['context' => $context]);

        return html_writer::tag('div', html_writer::link($url, $formattedname, ['target' => '_blank']));
    }
}
