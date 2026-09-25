<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode(['logged_in' => isLoggedIn()]);
