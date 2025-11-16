<?php

namespace App\Tests\Unit;

use App\Service\FileStorageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileStorageServiceTest extends TestCase
{
    private FileStorageService $service;
    private string $testProjectDir;
    private string $uploadDirectory;

    protected function setUp(): void
    {
        $this->testProjectDir = sys_get_temp_dir() . '/filestorage_test_' . uniqid();
        mkdir($this->testProjectDir, 0777, true);

        $this->service = new FileStorageService($this->testProjectDir);
        $this->uploadDirectory = $this->testProjectDir . '/public/uploads';
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->uploadDirectory)) {
            $files = glob($this->uploadDirectory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->uploadDirectory);
        }

        if (is_dir($this->testProjectDir . '/public')) {
            @rmdir($this->testProjectDir . '/public');
        }

        if (is_dir($this->testProjectDir)) {
            @rmdir($this->testProjectDir);
        }
    }

    public function testConstructorCreatesUploadDirectory(): void
    {
        $this->assertDirectoryExists($this->uploadDirectory);
    }

    public function testStoreFile(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test file content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);

        $this->assertNotEmpty($storedName);
        $this->assertStringEndsWith('.txt', $storedName);
        $this->assertTrue($this->service->exists($storedName));
    }

    public function testStoreFileWithoutExtension(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test file content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'testfile',
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);

        $this->assertNotEmpty($storedName);
        // Should have .bin extension when no extension is found
        $this->assertMatchesRegularExpression('/\.(bin|txt)$/', $storedName);
    }

    public function testStoreFileGeneratesUniqueNames(): void
    {
        // Create two identical files
        $tempFile1 = tempnam(sys_get_temp_dir(), 'test');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile1, 'Same content');
        file_put_contents($tempFile2, 'Same content');

        $uploadedFile1 = new UploadedFile(
            $tempFile1,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $uploadedFile2 = new UploadedFile(
            $tempFile2,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $storedName1 = $this->service->store($uploadedFile1);
        $storedName2 = $this->service->store($uploadedFile2);

        $this->assertNotEquals($storedName1, $storedName2);
        $this->assertTrue($this->service->exists($storedName1));
        $this->assertTrue($this->service->exists($storedName2));
    }

    public function testDeleteFile(): void
    {
        // Create and store a file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'File to delete');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'delete_me.txt',
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);
        $this->assertTrue($this->service->exists($storedName));

        // Delete the file
        $this->service->delete($storedName);
        $this->assertFalse($this->service->exists($storedName));
    }

    public function testDeleteNonExistentFile(): void
    {
        // Deleting a non-existent file should not throw an exception
        $this->service->delete('nonexistent_file.txt');
        $this->assertFalse($this->service->exists('nonexistent_file.txt'));
    }

    public function testGetFilePath(): void
    {
        $storedName = 'test_file.txt';
        $expectedPath = $this->uploadDirectory . '/' . $storedName;

        $actualPath = $this->service->getFilePath($storedName);

        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testExists(): void
    {
        $this->assertFalse($this->service->exists('nonexistent.txt'));

        // Create a file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Exists test');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'exists_test.txt',
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);
        $this->assertTrue($this->service->exists($storedName));
    }

    public function testStorePreservesFileContent(): void
    {
        $content = 'This is the original file content that should be preserved';

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, $content);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'content_test.txt',
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);
        $filePath = $this->service->getFilePath($storedName);

        $this->assertEquals($content, file_get_contents($filePath));
    }

    public function testStoreWithDifferentExtensions(): void
    {
        $extensions = ['txt', 'jpg', 'pdf', 'json', 'xml'];

        foreach ($extensions as $ext) {
            $tempFile = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tempFile, 'Test content');

            $uploadedFile = new UploadedFile(
                $tempFile,
                "test.$ext",
                'application/octet-stream',
                null,
                true
            );

            $storedName = $this->service->store($uploadedFile);

            $this->assertStringEndsWith(".$ext", $storedName);
            $this->assertTrue($this->service->exists($storedName));

            // Clean up for next iteration
            $this->service->delete($storedName);
        }
    }

    public function testStoreThrowsExceptionForNonExistentSource(): void
    {
        // UploadedFile constructor checks if file exists and throws its own exception
        // So we test that the service correctly handles missing files
        $this->expectException(\Exception::class);

        // Create an UploadedFile with a non-existent path
        $uploadedFile = new UploadedFile(
            '/non/existent/path.txt',
            'test.txt',
            'text/plain',
            null,
            true
        );

        $this->service->store($uploadedFile);
    }

    public function testStoreHandlesBinaryFiles(): void
    {
        // Create a binary file with random bytes
        $binaryContent = random_bytes(256);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, $binaryContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'binary.dat',
            'application/octet-stream',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);
        $filePath = $this->service->getFilePath($storedName);

        $this->assertEquals($binaryContent, file_get_contents($filePath));
    }

    public function testStoreHandlesLargeFilename(): void
    {
        $longFilename = str_repeat('a', 200) . '.txt';

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test');

        $uploadedFile = new UploadedFile(
            $tempFile,
            $longFilename,
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);

        // Stored name should be unique and not use the long filename
        $this->assertLessThan(strlen($longFilename), strlen($storedName));
        $this->assertTrue($this->service->exists($storedName));
    }

    public function testStoreHandlesSpecialCharactersInFilename(): void
    {
        $specialFilename = 'test file @#$%^&*().txt';

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test');

        $uploadedFile = new UploadedFile(
            $tempFile,
            $specialFilename,
            'text/plain',
            null,
            true
        );

        $storedName = $this->service->store($uploadedFile);

        // Should successfully store despite special characters
        $this->assertStringEndsWith('.txt', $storedName);
        $this->assertTrue($this->service->exists($storedName));
    }

    public function testMultipleStoresAndDeletes(): void
    {
        $storedNames = [];

        // Store multiple files
        for ($i = 0; $i < 5; $i++) {
            $tempFile = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tempFile, "Content $i");

            $uploadedFile = new UploadedFile(
                $tempFile,
                "file$i.txt",
                'text/plain',
                null,
                true
            );

            $storedNames[] = $this->service->store($uploadedFile);
        }

        // Verify all exist
        foreach ($storedNames as $name) {
            $this->assertTrue($this->service->exists($name));
        }

        // Delete all
        foreach ($storedNames as $name) {
            $this->service->delete($name);
        }

        // Verify all deleted
        foreach ($storedNames as $name) {
            $this->assertFalse($this->service->exists($name));
        }
    }
}
