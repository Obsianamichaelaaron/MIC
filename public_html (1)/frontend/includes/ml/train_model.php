<?php
require_once '../config/database.php';
require_once 'python_ranking.php';

header('Content-Type: application/json');

$python_ranking = new PythonRankingSystem();
$result = $python_ranking->trainModel();

echo json_encode($result);
?>