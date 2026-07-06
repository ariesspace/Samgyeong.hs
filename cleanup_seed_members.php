<?php
require __DIR__ . '/src/bootstrap.php';
$db = Database::connect();
$names = ['???','???','???','???','???','???','???','???','???'];
$stmt = $db->prepare('DELETE FROM hall_members WHERE (user_id IS NULL OR user_id = 0) AND student_name = ?');
$total = 0;
foreach ($names as $name) {
    $stmt->execute([$name]);
    $total += $stmt->rowCount();
}
echo "deleted=" . $total . PHP_EOL;
