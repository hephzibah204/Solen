<?php
require_once __DIR__ . '/../includes/db.php';
$users = db_query("SELECT email, role FROM users LIMIT 10");
header('Content-Type: application/json');
echo json_encode($users, JSON_PRETTY_PRINT);
