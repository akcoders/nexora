<?php

return [
    'require_attendance_for_task_actions' => env('NEXORA_REQUIRE_ATTENDANCE', true),
    'default_geofence_radius' => (int) env('NEXORA_GEOFENCE_RADIUS', 200),
    'production_api_url' => 'https://nexora.webignitors.in/api/v1/',
];
