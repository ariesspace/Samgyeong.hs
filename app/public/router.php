<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if (str_starts_with($path, '/uploads/')) {
    $upload = realpath(__DIR__ . '/../storage/uploads/' . basename($path));
    $uploadDir = realpath(__DIR__ . '/../storage/uploads');
    if ($upload && $uploadDir && str_starts_with($upload, $uploadDir) && is_file($upload)) {
        $types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];
        $type = $types[strtolower(pathinfo($upload, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        header('Content-Type: ' . ($type ?: 'application/octet-stream'));
        readfile($upload);
        return true;
    }
}

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
