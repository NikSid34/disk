<?php


namespace app\service;


use app\entity\File;
use app\enum\FileType;
use app\enum\ThumbnailQualityLevel;
use RuntimeException;


class ThumbnailService {
    public function isPreviewable(File $file): bool {
        return $file->fileType === FileType::Image;
    }

    public function ensureThumbnail(File $file, ThumbnailQualityLevel $qualityLevel): string {
        if (!$this->isPreviewable($file)) {
            throw new RuntimeException('File does not support thumbnails');
        }

        $storageDir = dirname($file->fileObject->storagePath);
        $thumbnailPath = $storageDir . '/' . $file->fileObject->hash . '_thumb_' . $qualityLevel->value . '.jpg';

        if (!is_file($thumbnailPath)) {
            $this->createThumbnail(
                    $file->fileObject->storagePath,
                    $thumbnailPath,
                    $qualityLevel->value,
                    $qualityLevel->value
            );
        }

        return $thumbnailPath;
    }

    public function deleteCachedThumbnails(string $fileHash, string $storageDir): void {
        foreach (ThumbnailQualityLevel::cases() as $qualityLevel) {
            $thumbnailPath = $storageDir . '/' . $fileHash . '_thumb_' . $qualityLevel->value . '.jpg';
            if (is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }

    private function createThumbnail(string $sourcePath, string $destPath, int $width, int $height): void {
        $imageMeta = getimagesize($sourcePath);
        if ($imageMeta === false) {
            throw new RuntimeException('Failed to read source image size');
        }

        [$origWidth, $origHeight, $type] = $imageMeta;

        $image = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? imagecreatefrombmp($sourcePath) : false,
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Unsupported image type');
        }

        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
        imagejpeg($thumb, $destPath, 85);

        imagedestroy($image);
        imagedestroy($thumb);
    }
}
