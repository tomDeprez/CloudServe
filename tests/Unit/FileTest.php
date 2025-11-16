<?php

namespace App\Tests\Unit;

use App\Entity\File;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    public function testFileCreation(): void
    {
        $file = new File();
        $file->setFilename('test.txt');
        $file->setStoredName('abc123.txt');
        $file->setMimeType('text/plain');
        $file->setSize('1024');

        $this->assertEquals('test.txt', $file->getFilename());
        $this->assertEquals('abc123.txt', $file->getStoredName());
        $this->assertEquals('text/plain', $file->getMimeType());
        $this->assertEquals('1024', $file->getSize());
        $this->assertInstanceOf(\DateTimeImmutable::class, $file->getUploadedAt());
        $this->assertEquals('file', $file->getType());
        $this->assertFalse($file->isProcessing());
    }

    public function testFileWithUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $file = new File();
        $file->setUser($user);

        $this->assertSame($user, $file->getUser());
    }

    public function testFolderType(): void
    {
        $folder = new File();
        $folder->setFilename('Documents');
        $folder->setType('folder');

        $this->assertEquals('folder', $folder->getType());
        $this->assertTrue($folder->isFolder());
    }

    public function testFileType(): void
    {
        $file = new File();
        $file->setFilename('document.txt');
        $file->setType('file');

        $this->assertEquals('file', $file->getType());
        $this->assertFalse($file->isFolder());
    }

    public function testParentChildRelationship(): void
    {
        $parent = new File();
        $parent->setFilename('Parent Folder');
        $parent->setType('folder');

        $child = new File();
        $child->setFilename('child.txt');
        $child->setParent($parent);

        $this->assertSame($parent, $child->getParent());
    }

    public function testTextFileContent(): void
    {
        $file = new File();
        $file->setFilename('notes.txt');
        $file->setContent('Hello World');

        $this->assertEquals('Hello World', $file->getContent());
    }

    public function testThumbnail(): void
    {
        $file = new File();
        $file->setFilename('image.jpg');
        $file->setThumbnail('thumbnails/thumb_image.jpg');

        $this->assertEquals('thumbnails/thumb_image.jpg', $file->getThumbnail());
    }

    public function testFileHash(): void
    {
        $file = new File();
        $file->setFilename('document.pdf');
        $file->setHash('abc123def456');

        $this->assertEquals('abc123def456', $file->getHash());
    }

    public function testProcessingFlag(): void
    {
        $file = new File();
        $this->assertFalse($file->isProcessing());

        $file->setProcessing(true);
        $this->assertTrue($file->isProcessing());

        $file->setProcessing(false);
        $this->assertFalse($file->isProcessing());
    }

    public function testIsEditableForTextFiles(): void
    {
        $editableExtensions = ['txt', 'md', 'json', 'xml', 'csv', 'log', 'html', 'css', 'js', 'php', 'yml', 'yaml'];

        foreach ($editableExtensions as $ext) {
            $file = new File();
            $file->setFilename("test.$ext");
            $this->assertTrue($file->isEditable(), "File with extension .$ext should be editable");
        }
    }

    public function testIsNotEditableForBinaryFiles(): void
    {
        $nonEditableExtensions = ['jpg', 'png', 'pdf', 'mp4', 'zip', 'exe'];

        foreach ($nonEditableExtensions as $ext) {
            $file = new File();
            $file->setFilename("test.$ext");
            $this->assertFalse($file->isEditable(), "File with extension .$ext should not be editable");
        }
    }

    public function testGetFileTypeForImage(): void
    {
        $file = new File();
        $file->setFilename('photo.jpg');
        $file->setMimeType('image/jpeg');
        $file->setType('file');

        $this->assertEquals('image', $file->getFileType());
    }

    public function testGetFileTypeForVideo(): void
    {
        $file = new File();
        $file->setFilename('movie.mp4');
        $file->setMimeType('video/mp4');
        $file->setType('file');

        $this->assertEquals('video', $file->getFileType());
    }

    public function testGetFileTypeForAudio(): void
    {
        $file = new File();
        $file->setFilename('song.mp3');
        $file->setMimeType('audio/mpeg');
        $file->setType('file');

        $this->assertEquals('audio', $file->getFileType());
    }

    public function testGetFileTypeForPdf(): void
    {
        $file = new File();
        $file->setFilename('document.pdf');
        $file->setMimeType('application/pdf');
        $file->setType('file');

        $this->assertEquals('pdf', $file->getFileType());
    }

    public function testGetFileTypeForText(): void
    {
        $file = new File();
        $file->setFilename('readme.txt');
        $file->setMimeType('text/plain');
        $file->setType('file');

        $this->assertEquals('text', $file->getFileType());
    }

    public function testGetFileTypeForFolder(): void
    {
        $file = new File();
        $file->setFilename('Documents');
        $file->setType('folder');

        $this->assertEquals('folder', $file->getFileType());
    }

    public function testGetFileTypeForOther(): void
    {
        $file = new File();
        $file->setFilename('archive.zip');
        $file->setMimeType('application/zip');
        $file->setType('file');

        $this->assertEquals('other', $file->getFileType());
    }

    public function testImageTypeByExtension(): void
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

        foreach ($imageExtensions as $ext) {
            $file = new File();
            $file->setFilename("image.$ext");
            $file->setMimeType('application/octet-stream');
            $file->setType('file');

            $this->assertEquals('image', $file->getFileType(), "Extension .$ext should be detected as image");
        }
    }

    public function testVideoTypeByExtension(): void
    {
        $videoExtensions = ['mp4', 'webm', 'avi', 'mov', 'mkv'];

        foreach ($videoExtensions as $ext) {
            $file = new File();
            $file->setFilename("video.$ext");
            $file->setMimeType('application/octet-stream');
            $file->setType('file');

            $this->assertEquals('video', $file->getFileType(), "Extension .$ext should be detected as video");
        }
    }

    public function testAudioTypeByExtension(): void
    {
        $audioExtensions = ['mp3', 'wav', 'flac', 'm4a', 'aac'];

        foreach ($audioExtensions as $ext) {
            $file = new File();
            $file->setFilename("audio.$ext");
            $file->setMimeType('application/octet-stream');
            $file->setType('file');

            $this->assertEquals('audio', $file->getFileType(), "Extension .$ext should be detected as audio");
        }

        // OGG is ambiguous - defaults to video without proper mime type
        $file = new File();
        $file->setFilename("audio.ogg");
        $file->setMimeType('application/octet-stream');
        $file->setType('file');
        $this->assertEquals('video', $file->getFileType(), "Extension .ogg without audio mime type defaults to video");
    }

    public function testImageTypeByMimeType(): void
    {
        $file = new File();
        $file->setFilename('unknown');
        $file->setMimeType('image/png');
        $file->setType('file');

        $this->assertEquals('image', $file->getFileType());
    }

    public function testVideoTypeByMimeType(): void
    {
        $file = new File();
        $file->setFilename('unknown');
        $file->setMimeType('video/mp4');
        $file->setType('file');

        $this->assertEquals('video', $file->getFileType());
    }

    public function testAudioTypeByMimeType(): void
    {
        $file = new File();
        $file->setFilename('unknown');
        $file->setMimeType('audio/mpeg');
        $file->setType('file');

        $this->assertEquals('audio', $file->getFileType());
    }

    public function testTextTypeByMimeType(): void
    {
        $file = new File();
        $file->setFilename('unknown');
        $file->setMimeType('text/plain');
        $file->setType('file');

        $this->assertEquals('text', $file->getFileType());
    }

    public function testUploadedAtDefaultValue(): void
    {
        $before = new \DateTimeImmutable();
        $file = new File();
        $after = new \DateTimeImmutable();

        $uploadedAt = $file->getUploadedAt();
        $this->assertInstanceOf(\DateTimeImmutable::class, $uploadedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $uploadedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $uploadedAt->getTimestamp());
    }

    public function testSetCustomUploadedAt(): void
    {
        $customDate = new \DateTimeImmutable('2024-01-01 12:00:00');
        $file = new File();
        $file->setUploadedAt($customDate);

        $this->assertEquals($customDate, $file->getUploadedAt());
    }

    public function testCaseInsensitiveExtensions(): void
    {
        $file = new File();
        $file->setFilename('IMAGE.JPG');
        $file->setMimeType('image/jpeg');
        $file->setType('file');

        $this->assertEquals('image', $file->getFileType());
        $this->assertTrue($file->isEditable() === false);
    }

    public function testCaseInsensitiveEditableExtensions(): void
    {
        $file = new File();
        $file->setFilename('README.TXT');
        $file->setType('file');

        $this->assertTrue($file->isEditable());
    }
}
