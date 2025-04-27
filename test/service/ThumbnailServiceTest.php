<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\FileType;
use app\enum\ThumbnailQualityLevel;
use app\service\ThumbnailService;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use RuntimeException;

class ThumbnailServiceTest extends BaseTestCase {
    public function testIsPreviewableReturnsTrueOnlyForImages(): void {
        // region Arrange.
        $service = new ThumbnailService();
        $image = TestEntityFactory::createFile(['fileType' => FileType::Image, 'name' => 'image.jpg', 'extension' => 'jpg']);
        $text = TestEntityFactory::createFile(['fileType' => FileType::Text]);
        // endregion.

        // region Act.
        $previewableImage = $service->isPreviewable($image);
        $previewableText = $service->isPreviewable($text);
        // endregion.

        // region Assert.
        $this->assertTrue($previewableImage);
        $this->assertFalse($previewableText);
        // endregion.
    }

    public function testEnsureThumbnailCreatesAndReusesCachedThumbnail(): void {
        // region Arrange.
        $service = new ThumbnailService();
        $directory = $this->createTemporaryDirectory('simpledisk-thumbnail-');
        $sourcePath = $directory . '/source.jpg';
        $image = imagecreatetruecolor(8, 8);
        imagejpeg($image, $sourcePath, 90);
        imagedestroy($image);

        $file = TestEntityFactory::createFile([
                'name' => 'photo.jpg',
                'extension' => 'jpg',
                'fileType' => FileType::Image,
                'fileObject' => TestEntityFactory::createFileObject([
                        'hash' => 'image-hash',
                        'storagePath' => $sourcePath,
                        'contentType' => 'image/jpeg',
                ]),
        ]);
        // endregion.

        // region Act.
        $thumbnailPath = $service->ensureThumbnail($file, ThumbnailQualityLevel::Low);
        $secondCallPath = $service->ensureThumbnail($file, ThumbnailQualityLevel::Low);
        // endregion.

        // region Assert.
        $this->assertFileExists($thumbnailPath);
        $this->assertSame($thumbnailPath, $secondCallPath);
        // endregion.
    }

    public function testEnsureThumbnailThrowsForNonPreviewableFiles(): void {
        // region Arrange.
        $service = new ThumbnailService();
        $file = TestEntityFactory::createFile(['fileType' => FileType::Pdf, 'name' => 'report.pdf', 'extension' => 'pdf']);
        // endregion.

        // region Act.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File does not support thumbnails');
        $service->ensureThumbnail($file, ThumbnailQualityLevel::Low);
        // endregion.
    }

    public function testDeleteCachedThumbnailsRemovesAllKnownVariants(): void {
        // region Arrange.
        $service = new ThumbnailService();
        $directory = $this->createTemporaryDirectory('simpledisk-delete-thumbnails-');
        foreach (ThumbnailQualityLevel::cases() as $qualityLevel) {
            file_put_contents($directory . '/file-hash_thumb_' . $qualityLevel->value . '.jpg', 'thumb');
        }
        // endregion.

        // region Act.
        $service->deleteCachedThumbnails('file-hash', $directory);
        // endregion.

        // region Assert.
        foreach (ThumbnailQualityLevel::cases() as $qualityLevel) {
            $this->assertFileDoesNotExist($directory . '/file-hash_thumb_' . $qualityLevel->value . '.jpg');
        }
        // endregion.
    }
}
