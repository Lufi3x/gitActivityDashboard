<?php
require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");

$filesToDelete = [
    __DIR__ . '/all_time_projects_cache.json',
    __DIR__ . '/cache.json',
    dirname(__DIR__) . '/cache.json'
];

$deletedFiles = [];
foreach ($filesToDelete as $file) {
    if (file_exists($file)) {
        if (@unlink($file)) {
            $deletedFiles[] = basename($file);
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Sistem önbelleği başarıyla temizlendi.',
    'deleted_files' => $deletedFiles,
    'timestamp' => date('H:i:s - d.m.Y')
], JSON_UNESCAPED_UNICODE);
