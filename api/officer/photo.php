<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_method('POST');
require_role(['admin']);

db()->exec(
    "CREATE TABLE IF NOT EXISTS officer_profile (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        designation VARCHAR(190) NOT NULL,
        join_date DATE NULL,
        telephone VARCHAR(64) NULL,
        mobile VARCHAR(64) NULL,
        email VARCHAR(190) NULL,
        photo_path VARCHAR(255) NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
db()->exec(
    "INSERT IGNORE INTO officer_profile (id, name, designation, photo_path)
     VALUES (1, 'জনাব মুহাম্মদ আব্দুল্লাহ আল মুমিন', 'উপজেলা নির্বাহী অফিসার', 'media/profile.jpeg')"
);

if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
    json_error('Photo file is required', 400);
}

$file = $_FILES['photo'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_error('Photo upload failed', 400);
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    json_error('Invalid uploaded file', 400);
}

$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > 2 * 1024 * 1024) {
    json_error('Photo size must be under 2MB', 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmpPath);
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => '',
};
if ($ext === '') {
    json_error('Only JPG, PNG or WEBP images are allowed', 400);
}

$uploadDir = dirname(__DIR__, 2) . '/public/media/uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    json_error('Failed to create upload directory', 500);
}

$fileName = 'officer_profile.' . $ext;
$destPath = $uploadDir . '/' . $fileName;
if (!move_uploaded_file($tmpPath, $destPath)) {
    json_error('Failed to save uploaded photo', 500);
}

$relativePath = 'media/uploads/' . $fileName;
$stmt = db()->prepare('UPDATE officer_profile SET photo_path = :photo WHERE id = 1');
$stmt->execute([':photo' => $relativePath]);

json_response(['ok' => true, 'photoPath' => $relativePath]);
