<?php
$capabilities = array(

    'block/student_progress:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ),
    ),

    'block/student_progress:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ),
    ),
);
