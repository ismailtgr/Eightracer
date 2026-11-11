<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    'thinking_level' => env('GEMINI_THINKING_LEVEL', 'minimal'),
    'chat_requests_per_minute' => (int) env('SCHOLARSHIP_CHAT_REQUESTS_PER_MINUTE', 30),
    'chat_llm_per_minute' => (int) env('SCHOLARSHIP_CHAT_LLM_PER_MINUTE', 5),
    'chat_cache_seconds' => (int) env('SCHOLARSHIP_CHAT_CACHE_SECONDS', 120),
    'timeout' => (int) env('GEMINI_TIMEOUT', 90),
];
