<?php

namespace App\Tests\Unit;

use App\Service\ThumbnailService;
use PHPUnit\Framework\TestCase;

class ThumbnailServiceTest extends TestCase
{
    private ThumbnailService $thumbnailService;
    private string $testUploadDir;
    private string $testThumbnailDir;

    protected function setUp(): void
    {
        $projectDir = sys_get_temp_dir() . '/cloudserve_test_' . uniqid();
        mkdir($projectDir);

        $this->testUploadDir = $projectDir . '/public/uploads';
        $this->testThumbnailDir = $projectDir . '/public/uploads/thumbnails';

        mkdir($this->testUploadDir, 0777, true);

        $this->thumbnailService = new ThumbnailService($projectDir);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testThumbnailDir)) {
            $files = glob($this->testThumbnailDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testThumbnailDir);
        }

        if (is_dir($this->testUploadDir)) {
            $files = glob($this->testUploadDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testUploadDir);
        }

        // Clean up parent directory
        $projectDir = dirname($this->testUploadDir);
        if (is_dir($projectDir . '/public')) {
            @rmdir($projectDir . '/public');
        }
        if (is_dir($projectDir)) {
            @rmdir($projectDir);
        }
    }

    public function testGenerateThumbnailForPngImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is not available in GD.');
        }

        // Create a simple PNG image
        $image = imagecreatetruecolor(400, 300);
        $backgroundColor = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $backgroundColor);

        $testImagePath = $this->testUploadDir . '/test_image.png';
        imagepng($image, $testImagePath);
        imagedestroy($image);

        // Generate thumbnail
        $thumbnailPath = $this->thumbnailService->generateThumbnail('test_image.png', 200, 200);

        $this->assertNotNull($thumbnailPath);
        $this->assertEquals('thumbnails/thumb_test_image.webp', $thumbnailPath);

        $fullThumbnailPath = $this->testThumbnailDir . '/thumb_test_image.webp';
        $this->assertFileExists($fullThumbnailPath);

        // Verify thumbnail dimensions
        $thumbnailInfo = getimagesize($fullThumbnailPath);
        $this->assertLessThanOrEqual(200, $thumbnailInfo[0]);
        $this->assertLessThanOrEqual(200, $thumbnailInfo[1]);
    }

    public function testGenerateThumbnailForJpegImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is not available in GD.');
        }

        // Create a simple JPEG image
        $image = imagecreatetruecolor(500, 400);
        $backgroundColor = imagecolorallocate($image, 0, 255, 0);
        imagefill($image, 0, 0, $backgroundColor);

        $testImagePath = $this->testUploadDir . '/test_image.jpg';
        imagejpeg($image, $testImagePath);
        imagedestroy($image);

        // Generate thumbnail
        $thumbnailPath = $this->thumbnailService->generateThumbnail('test_image.jpg', 200, 200);

        $this->assertNotNull($thumbnailPath);
        $this->assertEquals('thumbnails/thumb_test_image.webp', $thumbnailPath);

        $fullThumbnailPath = $this->testThumbnailDir . '/thumb_test_image.webp';
        $this->assertFileExists($fullThumbnailPath);
    }

    public function testGenerateThumbnailForNonExistentFile(): void
    {
        $thumbnailPath = $this->thumbnailService->generateThumbnail('nonexistent.png', 200, 200);

        $this->assertNull($thumbnailPath);
    }

    public function testGenerateThumbnailForNonImageFile(): void
    {
        // Create a text file
        $testFilePath = $this->testUploadDir . '/test.txt';
        file_put_contents($testFilePath, 'This is not an image');

        $thumbnailPath = $this->thumbnailService->generateThumbnail('test.txt', 200, 200);

        $this->assertNull($thumbnailPath);
    }

    public function testThumbnailExists(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create and generate thumbnail
        $image = imagecreatetruecolor(300, 300);
        $testImagePath = $this->testUploadDir . '/exists_test.png';
        imagepng($image, $testImagePath);
        imagedestroy($image);

        $thumbnailPath = $this->thumbnailService->generateThumbnail('exists_test.png', 200, 200);

        $this->assertTrue($this->thumbnailService->thumbnailExists($thumbnailPath));
        $this->assertFalse($this->thumbnailService->thumbnailExists('thumbnails/nonexistent.png'));
    }

    public function testDeleteThumbnail(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create and generate thumbnail
        $image = imagecreatetruecolor(300, 300);
        $testImagePath = $this->testUploadDir . '/delete_test.png';
        imagepng($image, $testImagePath);
        imagedestroy($image);

        $thumbnailPath = $this->thumbnailService->generateThumbnail('delete_test.png', 200, 200);

        $this->assertTrue($this->thumbnailService->thumbnailExists($thumbnailPath));

        // Delete the thumbnail
        $this->thumbnailService->deleteThumbnail($thumbnailPath);

        $this->assertFalse($this->thumbnailService->thumbnailExists($thumbnailPath));
    }

    public function testGetThumbnailPath(): void
    {
        $thumbnailPath = $this->thumbnailService->getThumbnailPath('thumbnails/thumb_test.webp');

        $expectedPath = $this->testThumbnailDir . '/thumb_test.webp';
        $this->assertEquals($expectedPath, $thumbnailPath);
    }

    public function testGenerateThumbnailPreservesAspectRatio(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is not available in GD.');
        }

        // Create a wide image (800x200)
        $image = imagecreatetruecolor(800, 200);
        $testImagePath = $this->testUploadDir . '/wide_image.png';
        imagepng($image, $testImagePath);
        imagedestroy($image);

        $thumbnailPath = $this->thumbnailService->generateThumbnail('wide_image.png', 200, 200);

        $this->assertNotNull($thumbnailPath);

        $fullThumbnailPath = $this->testThumbnailDir . '/thumb_wide_image.webp';
        $thumbnailInfo = getimagesize($fullThumbnailPath);

        // The width should be 200 and height should be 50 (maintaining 4:1 ratio)
        $this->assertEquals(200, $thumbnailInfo[0]);
        $this->assertEquals(50, $thumbnailInfo[1]);
    }
}
