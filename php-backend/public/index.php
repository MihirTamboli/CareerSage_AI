<?php
header('Content-Type: application/json');
echo json_encode([
    'name' => 'CareerSage AI PHP Backend',
    'status' => 'running',
    'api' => 'http://localhost/career-guidance-system/php-backend/public/api.php'
]);
