<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/student_progress:viewblock' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
