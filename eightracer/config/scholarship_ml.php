<?php

return [
    // Local, deterministic catalog fitting. A missing profile/catalog safely uses the criterion baseline.
    'enabled' => env('SCHOLARSHIP_ML_ENABLED', true),
];
