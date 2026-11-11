<?php

return [
    'local_student_preview' => (bool) env('B4_LOCAL_STUDENT_PREVIEW', false),
    'school_email_domains' => array_values(array_filter(array_map(fn ($v) => strtolower(trim($v)), explode(',', (string) env('SCHOOL_EMAIL_DOMAINS', ''))))),
    'school_staff_emails' => array_values(array_filter(array_map(fn ($v) => strtolower(trim($v)), explode(',', (string) env('SCHOOL_STAFF_EMAILS', ''))))),
    'python_binary' => env('B4_PYTHON_BINARY', 'python'),
    'import_rows' => 2000,
    'import_sheets' => 50,
    'import_expiry_hours' => 24,
    'minimum_history_group' => 5,
];
