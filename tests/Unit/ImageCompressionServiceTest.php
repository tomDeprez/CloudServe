<?php

namespace App\Tests\Unit;

use App\Service\ImageCompressionService;
use PHPUnit\Framework\TestCase;

class ImageCompressionServiceTest extends TestCase
{
    private ImageCompressionService $service;
    private string $testDir;

    protected function setUp(): void
    {
        $this->service = new ImageCompressionService();
        $this->testDir = sys_get_temp_dir() . '/imagecompression_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testDir);
        }
    }

    public function testIsCompressibleImage(): void
    {
        $this->assertTrue($this->service->isCompressibleImage('image/jpeg'));
        $this->assertTrue($this->service->isCompressibleImage('image/png'));
        $this->assertTrue($this->service->isCompressibleImage('image/webp'));

        $this->assertFalse($this->service->isCompressibleImage('image/gif'));
        $this->assertFalse($this->service->isCompressibleImage('image/svg+xml'));
        $this->assertFalse($this->service->isCompressibleImage('video/mp4'));
        $this->assertFalse($this->service->isCompressibleImage('text/plain'));
    }

    public function testCompressJpegImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a test JPEG image
        $image = imagecreatetruecolor(800, 600);
        $backgroundColor = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $backgroundColor);

        $sourcePath = $this->testDir . '/test.jpg';
        $destPath = $this->testDir . '/compressed.jpg';

        imagejpeg($image, $sourcePath, 100);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/jpeg');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        // Verify the image is valid
        $imageInfo = getimagesize($destPath);
        $this->assertIsArray($imageInfo);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }

    public function testCompressPngImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a test PNG image
        $image = imagecreatetruecolor(800, 600);
        $backgroundColor = imagecolorallocate($image, 0, 255, 0);
        imagefill($image, 0, 0, $backgroundColor);

        $sourcePath = $this->testDir . '/test.png';
        $destPath = $this->testDir . '/compressed.png';

        imagepng($image, $sourcePath, 0);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/png');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        // Verify the image is valid
        $imageInfo = getimagesize($destPath);
        $this->assertIsArray($imageInfo);
        $this->assertEquals('image/png', $imageInfo['mime']);
    }

    public function testCompressWebpImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is not available in GD.');
        }

        // Create a test WebP image
        $image = imagecreatetruecolor(800, 600);
        $backgroundColor = imagecolorallocate($image, 0, 0, 255);
        imagefill($image, 0, 0, $backgroundColor);

        $sourcePath = $this->testDir . '/test.webp';
        $destPath = $this->testDir . '/compressed.webp';

        imagewebp($image, $sourcePath, 100);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/webp');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);
    }

    public function testCompressInPlace(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a test JPEG image
        $image = imagecreatetruecolor(800, 600);
        $sourcePath = $this->testDir . '/test_inplace.jpg';

        imagejpeg($image, $sourcePath, 100);
        imagedestroy($image);

        $originalSize = filesize($sourcePath);

        // Compress in place (same source and destination)
        $result = $this->service->compressImage($sourcePath, $sourcePath, 'image/jpeg');

        $this->assertTrue($result);
        $this->assertFileExists($sourcePath);

        // Compressed file should typically be smaller
        $compressedSize = filesize($sourcePath);
        $this->assertLessThanOrEqual($originalSize, $compressedSize);
    }

    public function testCompressNonExistentFile(): void
    {
        $result = $this->service->compressImage(
            $this->testDir . '/nonexistent.jpg',
            $this->testDir . '/output.jpg',
            'image/jpeg'
        );

        $this->assertFalse($result);
    }

    public function testCompressInvalidImageFile(): void
    {
        // Create a text file pretending to be an image
        $sourcePath = $this->testDir . '/fake.jpg';
        file_put_contents($sourcePath, 'This is not an image');

        $result = $this->service->compressImage(
            $sourcePath,
            $this->testDir . '/output.jpg',
            'image/jpeg'
        );

        $this->assertFalse($result);
    }

    public function testCompressGifReturnsTrue(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a test GIF image
        $image = imagecreatetruecolor(100, 100);
        $sourcePath = $this->testDir . '/test.gif';

        imagegif($image, $sourcePath);
        imagedestroy($image);

        // GIF compression should return false (to preserve animation)
        $result = $this->service->compressImage(
            $sourcePath,
            $this->testDir . '/compressed.gif',
            'image/gif'
        );

        $this->assertFalse($result);
    }

    public function testCompressLargeImageResizes(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a very large image (5000x5000)
        $image = imagecreatetruecolor(5000, 5000);
        $sourcePath = $this->testDir . '/large.jpg';
        $destPath = $this->testDir . '/resized.jpg';

        imagejpeg($image, $sourcePath, 100);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/jpeg');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        // Check that image was resized to max dimensions
        $imageInfo = getimagesize($destPath);
        $this->assertLessThanOrEqual(4000, $imageInfo[0], 'Width should be <= 4000');
        $this->assertLessThanOrEqual(4000, $imageInfo[1], 'Height should be <= 4000');
    }

    public function testCompressPreservesAspectRatio(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a wide image (6000x2000) with 3:1 ratio
        $image = imagecreatetruecolor(6000, 2000);
        $sourcePath = $this->testDir . '/wide.jpg';
        $destPath = $this->testDir . '/resized_wide.jpg';

        imagejpeg($image, $sourcePath, 100);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/jpeg');

        $this->assertTrue($result);

        // Check aspect ratio is preserved
        $imageInfo = getimagesize($destPath);
        $ratio = $imageInfo[0] / $imageInfo[1];
        $this->assertEqualsWithDelta(3.0, $ratio, 0.1, 'Aspect ratio should be preserved');
    }

    public function testCompressPngPreservesTransparency(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a PNG with transparency
        $image = imagecreatetruecolor(200, 200);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, 200, 200, $transparent);

        $sourcePath = $this->testDir . '/transparent.png';
        $destPath = $this->testDir . '/compressed_transparent.png';

        imagepng($image, $sourcePath);
        imagedestroy($image);

        $result = $this->service->compressImage($sourcePath, $destPath, 'image/png');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);
    }

    public function testGetCompressionInfo(): void
    {
        // Create two test files with different sizes
        $originalPath = $this->testDir . '/original.txt';
        $compressedPath = $this->testDir . '/compressed.txt';

        file_put_contents($originalPath, str_repeat('x', 1000));
        file_put_contents($compressedPath, str_repeat('x', 800));

        $info = $this->service->getCompressionInfo($originalPath, $compressedPath);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('original_size', $info);
        $this->assertArrayHasKey('compressed_size', $info);
        $this->assertArrayHasKey('saved_bytes', $info);
        $this->assertArrayHasKey('saved_percent', $info);

        $this->assertEquals(1000, $info['original_size']);
        $this->assertEquals(800, $info['compressed_size']);
        $this->assertEquals(200, $info['saved_bytes']);
        $this->assertEquals(20.0, $info['saved_percent']);
    }

    public function testGetCompressionInfoWithSameSize(): void
    {
        // Create two identical files
        $originalPath = $this->testDir . '/original2.txt';
        $compressedPath = $this->testDir . '/compressed2.txt';

        file_put_contents($originalPath, 'test content');
        file_put_contents($compressedPath, 'test content');

        $info = $this->service->getCompressionInfo($originalPath, $compressedPath);

        $this->assertEquals(0, $info['saved_bytes']);
        $this->assertEquals(0.0, $info['saved_percent']);
    }

    public function testGetCompressionInfoWithLargerCompressed(): void
    {
        // Create files where "compressed" is actually larger
        $originalPath = $this->testDir . '/original3.txt';
        $compressedPath = $this->testDir . '/compressed3.txt';

        file_put_contents($originalPath, 'short');
        file_put_contents($compressedPath, 'much longer content');

        $info = $this->service->getCompressionInfo($originalPath, $compressedPath);

        $this->assertLessThan(0, $info['saved_bytes']);
        $this->assertLessThan(0, $info['saved_percent']);
    }

    public function testCompressAutoDetectsMimeType(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a JPEG without specifying mime type
        $image = imagecreatetruecolor(200, 200);
        $sourcePath = $this->testDir . '/autodetect.jpg';
        $destPath = $this->testDir . '/autodetect_out.jpg';

        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        // Call without mime type (should auto-detect)
        $result = $this->service->compressImage($sourcePath, $destPath, null);

        $this->assertTrue($result);
        $this->assertFileExists($destPath);
    }

    public function testCompressUnsupportedMimeType(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        // Create a test file
        $sourcePath = $this->testDir . '/test.bmp';
        file_put_contents($sourcePath, 'fake bmp');

        $result = $this->service->compressImage(
            $sourcePath,
            $this->testDir . '/output.bmp',
            'image/bmp'
        );

        $this->assertFalse($result);
    }
}
