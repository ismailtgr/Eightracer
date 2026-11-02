<?php

return [
    'email_otp_enabled' => (bool) env('EMAIL_OTP_ENABLED', false),
    'google_client_id' => env('GOOGLE_CLIENT_ID'),
    'otp_seconds' => 600, 'otp_attempts' => 5, 'reset_grant_seconds' => 300,
    'minimum_public_cohort' => max(5, (int) env('MINIMUM_PUBLIC_COHORT', 5)),
    'contact_email' => env('SCHOOL_SUPPORT_EMAIL'),
];
