<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    'thinking_level' => env('GEMINI_THINKING_LEVEL', 'minimal'),
    'timeout' => (int) env('GEMINI_TIMEOUT', 90),
];
