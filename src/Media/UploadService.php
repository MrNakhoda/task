<?php

declare(strict_types=1);

namespace App\Media;

use App\Support\Env;
use RuntimeException;

final class UploadService
{
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeImage(array $file, string $folder = 'images'): string
    {
        $folder = trim($folder, '/');
        if (preg_match('/^[a-z0-9][a-z0-9\/-]*$/', $folder) !== 1 || str_contains($folder, '..')) {
            throw new RuntimeException('Upload folder is invalid.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $maxBytes = max(1, (int) (Env::get('UPLOAD_MAX_IMAGE_BYTES', '5000000') ?? '5000000'));
        $size = (int) ($file['size'] ?? 0);
        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > $maxBytes || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Uploaded image is invalid or too large.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $imageInfo = @getimagesize($tmp);
        if (!isset(self::IMAGE_TYPES[$mime]) || $imageInfo === false || (string) ($imageInfo['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Only valid JPEG, PNG and WEBP images are accepted.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $height > intdiv(30_000_000, $width)) {
            throw new RuntimeException('Image dimensions are not allowed.');
        }

        $relative = 'uploads/' . $folder . '/' . date('Y/m') . '/' . bin2hex(random_bytes(20)) . '.' . self::IMAGE_TYPES[$mime];
        $destination = APP_ROOT . '/public/' . $relative;
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload directory could not be created.');
        }
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('Uploaded image could not be stored.');
        }
        chmod($destination, 0644);
        return $relative;
    }

    public function delete(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return false;
        }
        $base = realpath(APP_ROOT . '/public/uploads');
        $target = realpath(APP_ROOT . '/public/' . $relativePath);
        if ($base === false || $target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            return false;
        }
        return is_file($target) && unlink($target);
    }
}
