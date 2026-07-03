<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Admin user avatars.
 *
 * Avatars are stored as Public/uploads/avatars/{user_id}.jpg (128x128,
 * center-cropped JPEG). No DB column is needed: the file's existence is the
 * source of truth and the user id is the filename.
 */
class AvatarHelper
{
    public const SIZE = 128;

    private static function dir(): string
    {
        return ROOT . 'Public' . DS . 'uploads' . DS . 'avatars' . DS;
    }

    private static function path(string $userId): string
    {
        // User ids are UUIDs; sanitize defensively before using in a path
        $safe = preg_replace('/[^a-f0-9\-]/i', '', $userId);
        return self::dir() . $safe . '.jpg';
    }

    /** Public URL for a user's avatar, or null when none uploaded. */
    public static function url(string $userId, string $baseUrl = ''): ?string
    {
        $file = self::path($userId);
        if (!is_file($file)) {
            return null;
        }
        // mtime query keeps browsers from caching a replaced avatar
        return $baseUrl . '/uploads/avatars/' . basename($file) . '?v=' . filemtime($file);
    }

    /**
     * Process an uploaded image ($_FILES entry) into a 128x128 JPEG avatar.
     * Returns null on success or a user-facing error message on failure.
     */
    public static function store(array $file, string $userId): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Upload failed. Please try again.';
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return 'Avatar must be 2 MB or smaller.';
        }

        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return 'Avatar must be a JPG, PNG, GIF, or WebP image.';
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
            default        => false,
        };
        if ($src === false) {
            return 'Could not read the image file.';
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $side    = min($w, $h);
        $srcX    = (int) (($w - $side) / 2);
        $srcY    = (int) (($h - $side) / 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        // White backdrop so transparent PNGs flatten cleanly to JPEG
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($src);

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            imagedestroy($dst);
            return 'Could not create the avatars directory.';
        }

        $ok = imagejpeg($dst, self::path($userId), 88);
        imagedestroy($dst);

        return $ok ? null : 'Could not save the avatar.';
    }

    /** Remove a user's avatar file (profile "remove" action and user deletion). */
    public static function remove(string $userId): void
    {
        $file = self::path($userId);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
