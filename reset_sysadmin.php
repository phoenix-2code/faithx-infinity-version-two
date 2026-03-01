<?php
require_once __DIR__ . '/config/database.php';

$hash = password_hash('FaithX2025!', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'sysadmin'");
$stmt->execute([$hash]);

echo "Password for sysadmin updated to 'FaithX2025!' (Hash: $hash)\n";
