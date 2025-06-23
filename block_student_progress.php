<?php

class block_student_progress extends block_base {    

    public function init() {
        $this->title = get_string('pluginname', 'block_student_progress');
    }

    public function get_content() {
        global $COURSE, $USER, $DB, $PAGE;
    
        $coursename = format_string($COURSE->fullname);
        $courseid = $COURSE->id;
        $userid = $USER->id;

        $PAGE->requires->css(new moodle_url('/blocks/student_progress/styles.css'));

        $userlearning = $this->get_user_learning($userid); // Learning type of user

        $sections = $this->get_user_sections($userid);

        list($finishsections, $totalsections) = $this->get_progress_data($userid);

        // resources Progress
        list($finishresources, $totalresources) = $this->get_progress_resource($userid, $courseid);

        // Progress (%)
        $progreso = ($totalresources > 0) ? round(($finishresources * 100) / $totalresources) : 0;

        // motivational message
        $message = $this->get_motivational_message($progreso, $courseid);
        $duration = 20000;

        if ($this->content !== null) {
            return $this->content;
        }
    
        $this->content = new stdClass();
    
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

            <div style="margin-top: 10px;"><strong>Tipo de Aprendizaje</strong><br>' . $userlearning . '</div>
    
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

                if (!$sectionid) {
                    continue;
                }
                
                $resources = $this->get_user_resources ($userid, $sectionid);

            $sectionname = $section->section_name ?: "Tema sin nombre";

            $status = $section->section_status ?: "Por resolver";
            
            if($status == "Resuelto") {
                $colorstatus = "dot verde";
            } elseif ($status == "En progreso") {
                $colorstatus = "dot amarillo";
            } else {
                $colorstatus = "dot rojo";
            }

            $html .= '
                <tr class="tema-row">
                    <td class="tema-col">
                        <span class="toggle" id="icon-sub' . $idcontador . '" onclick="toggleSubtema(\'sub' . $idcontador . '\')">▶</span>
                        ' . $sectionname . '
                        <div class="subtemas hidden" id="sub' . $idcontador . '">';

                        if(!$resources) {
                            $html .= '<span>Sin recursos<span/>';
                        }
                            
                        $idcont = 1;
                        foreach ($resources as $resource) {
                            $resourcename = $resource->nombre_actividad ?: "Recurso sin nombre";
                            $moduleid = $resource->id_course_module ?: 0;
                            $modulename = $resource->tipo_modulo?: "";
                            $html .= $this->render_activity_link($modulename, $moduleid, $resourcename);
                            $idcont++;
                            $html .= '<br/>';
                        }

            $html .= '
                        </div>
                    </td>
                    <td class="tema-col dot-col"><span class="' . $colorstatus . '"></span></td>
                </tr>';
            $idcontador++;
        }
            $html .= '</table>
        </div>';
    
        $this->content->text = $html;
        $this->content->footer = '';
    
        return $this->content;
    }

    // Get the learning type of the user
    private function get_user_learning($userid) {
        global $DB;

        $sql = "SELECT
                    lt.name as user_learning
                FROM {learning_type_plg} lt
                JOIN {user_learning_plg} ul ON lt.id_learning_type = ul.id_learning
                JOIN {user} u ON ul.id_user = u.id
                WHERE u.id = :userid";
        $params = ['userid' => $userid];
        $learning = $DB->get_record_sql($sql, $params);

        $userlearning = $learning->user_learning;

        return $userlearning;
    }

    // Get the sections for the user
    private function get_user_sections($userid) {
        global $DB;

        $sql = "SELECT 
                s.id AS section_id,
                s.name AS section_name,
                us.status AS section_status
                FROM {course_sections} s
                JOIN {user_sections_plg} us ON s.id = us.id_section
                JOIN {user_learning_plg} ul ON us.id_user_learning = ul.id_user_learning
                JOIN {user} u ON ul.id_user = u.id
                WHERE u.id = :userid";

        $params = ['userid' => $userid];
        $sections = $DB->get_records_sql($sql, $params);

        return $sections;
    }

    // Get finished task
    private function get_progress_data($userid) {
        global $DB;

        $sql = "SELECT 
                    SUM(CASE WHEN us.status = 'Resuelto' THEN 1 ELSE 0 END) AS secciones_resueltas,
                    COUNT(us.id_user_section) AS total_secciones
                FROM {user_sections_plg} us
                JOIN {user_learning_plg} ul ON us.id_user_learning = ul.id_user_learning
                JOIN {user} u ON ul.id_user = u.id
                WHERE u.id = :userid";

                

        $params = ['userid' => $userid];
        $numbersections = $DB->get_record_sql($sql, $params);
        // Convert to int if there is a register
        $finishsections = isset($numbersections->secciones_resueltas) ? (int)$numbersections->secciones_resueltas : 0;
        $totalsections = isset($numbersections->total_secciones) ? (int)$numbersections->total_secciones : 0;

        return [$finishsections, $totalsections];
    }

    
    // Get the resources for the user
    private function get_user_resources ($userid, $sectionid) {
        global $DB;

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
                JOIN {learning_course_module_plg} lcm ON cm.id = lcm.id_course_module
                JOIN {user_learning_module_plg} ulcm ON lcm.id_lcm_learning = ulcm.id_learning_course_module
                JOIN {user_learning_plg} ul ON ulcm.id_user_learning = ul.id_user_learning
                JOIN {user} us ON ul.id_user = us.id
                JOIN {modules} m ON cm.module = m.id

                -- LEFT JOIN por cada tipo
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

                WHERE us.id = :userid AND cs.id = :sectionid";

        $params = ['userid' => $userid, 'sectionid' => $sectionid];
        $resources = $DB->get_records_sql($sql, $params);

        return $resources;
    }

    private function get_motivational_message($progress, $courseid) {
        global $DB;
    
        // Obtener todos los mensajes
        $sql = "SELECT * FROM {course_alerts_plg} ca WHERE ca.id_course = :courseid";
        $params = ['courseid' => $courseid];
        $messages = $DB->get_records_sql($sql, $params); // ← corregido
    
        // Obtener el número total de alertas
        $sql = "SELECT COUNT(ca.id_course_alerts) AS total_alerts FROM {course_alerts_plg} ca WHERE ca.id_course = :courseid";
        $number_alerts = $DB->get_record_sql($sql, $params);
        $totalalerts = isset($number_alerts->total_alerts) ? (int)$number_alerts->total_alerts : 0;
    
        if (!empty($messages)) {
            $idcontador = 1;
            foreach ($messages as $alert) {
                $percentage = 100 / $totalalerts;
                if ($progress < $percentage * $idcontador) {
                    return $alert->alert; // o el campo que quieras retornar
                }
                $idcontador++;
            }
        } else {
            // Mensajes por defecto si no hay registros
            if ($progress < 25) {
                return '¡Vamos! Recién comienzas. Cada paso cuenta.';
            } elseif ($progress < 50) {
                return '¡Buen inicio! Sigue avanzando paso a paso.';
            } elseif ($progress < 75) {
                return '¡Vas por buen camino! Ya completaste la mitad.';
            } else {
                return '¡Excelente trabajo! Ya casi terminas.';
            }
        }
    }
    
    /**
     * Genera un enlace HTML hacia una actividad o recurso de Moodle.
     *
     * @param string $modulename Nombre del módulo (por ejemplo: 'resource', 'quiz', 'assign').
     * @param int $cmid ID del course_module (cm.id).
     * @param string $resourcename Nombre visible del recurso o actividad.
     * @return string HTML del enlace.
     */
    private function render_activity_link(string $modulename, int $cmid, string $resourcename): string {
        $url = new moodle_url("/mod/{$modulename}/view.php", ['id' => $cmid]);
        $context = context_module::instance($cmid);
        $formattedname = format_string($resourcename, true, ['context' => $context]);

        return html_writer::tag('div', html_writer::link($url, $formattedname, ['target' => '_blank']));
    }

    // Get finished task
    private function get_progress_resource($userid, $courseid) {
        global $DB;

        $sql = "SELECT 
                    (SELECT COUNT(*) 
                    FROM {learning_course_module_plg} lcm
                    JOIN {course_modules} cm ON cm.id = lcm.id_course_module
                    JOIN {user_learning_module_plg} ulcm ON lcm.id_lcm_learning = ulcm.id_learning_course_module
                    JOIN {user_learning_plg} ul ON ulcm.id_user_learning = ul.id_user_learning
                    JOIN {user} u ON ul.id_user = u.id
                    WHERE ulcm.id_user_learning = (SELECT ul.id_user_learning FROM {user_learning_plg} ul JOIN {user} u ON ul.id_user = u.id WHERE u.id = :userid1) 
                    AND cm.course = :courseid1) AS total_asignados,

                    (SELECT COUNT(*) 
                    FROM {course_modules_completion} cmc
                    JOIN {learning_course_module_plg} lcm ON lcm.id_course_module = cmc.coursemoduleid
                    JOIN {course_modules} cm ON cm.id = lcm.id_course_module
                    JOIN {user_learning_module_plg} ulcm ON lcm.id_lcm_learning = ulcm.id_learning_course_module
                    JOIN {user_learning_plg} ul ON ulcm.id_user_learning = ul.id_user_learning
                    JOIN {user} u ON ul.id_user = u.id
                    WHERE cmc.userid = :userid2 AND cm.course = :courseid2 AND cmc.completionstate = 1) AS total_completados";

        $params = ['userid1' => $userid, 'courseid1' => $courseid, 'userid2' => $userid, 'courseid2' => $courseid];
        $numberresources = $DB->get_record_sql($sql, $params);
        // Convert to int if there is a register
        $finishresources = isset($numberresources->total_completados) ? (int)$numberresources->total_completados : 0;
        $totalresources = isset($numberresources->total_asignados) ? (int)$numberresources->total_asignados : 0;

        return [$finishresources, $totalresources];
    }

}
