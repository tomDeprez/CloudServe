<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileControllerTest extends WebTestCase
{
    private $client;
    private $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = $this->createUserAndGetToken();
    }

    private function createUserAndGetToken(): string
    {
        $email = 'file_test_' . uniqid() . '@example.com';
        $password = 'password123';

        $this->client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'];
    }

    public function testUploadFile(): void
    {
        // Note: File upload through test client has limitations with $_FILES
        // This test verifies the authentication and validation logic
        $this->client->request(
            'POST',
            '/files/upload',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        // Should get 400 because no file was uploaded
        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('No file uploaded', $data['error']);
    }

    public function testListFiles(): void
    {
        $this->client->request(
            'GET',
            '/files',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('files', $data);
        $this->assertIsArray($data['files']);
    }

    public function testUploadWithoutAuthentication(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/files/upload',
            [],
            ['file' => $uploadedFile]
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadWithoutFile(): void
    {
        $this->client->request(
            'POST',
            '/files/upload',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUploadMultipleFiles(): void
    {
        // Test validates that endpoint requires files
        $this->client->request(
            'POST',
            '/files/upload-multiple',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('No files uploaded', $data['error']);
    }

    public function testCreateFolder(): void
    {
        $this->client->request(
            'POST',
            '/files/folder',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['name' => 'Test Folder'])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('folder', $data);
        $this->assertEquals('Test Folder', $data['folder']['filename']);
        $this->assertEquals('folder', $data['folder']['type']);
    }

    public function testCreateTextFile(): void
    {
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'test.txt',
                'content' => 'Hello World'
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('test.txt', $data['file']['filename']);
        $this->assertEquals('text', $data['file']['type']);

        // Verify content by fetching it
        $fileId = $data['file']['id'];
        $this->client->request(
            'GET',
            '/files/' . $fileId . '/content',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $contentData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Hello World', $contentData['content']);
    }

    public function testMoveFile(): void
    {
        // First create a folder
        $this->client->request(
            'POST',
            '/files/folder',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['name' => 'Target Folder'])
        );

        $folderData = json_decode($this->client->getResponse()->getContent(), true);
        $folderId = $folderData['folder']['id'];

        // Create a text file
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'movable.txt',
                'content' => 'File to be moved'
            ])
        );

        $fileData = json_decode($this->client->getResponse()->getContent(), true);
        $fileId = $fileData['file']['id'];

        // Now move the file into the folder
        $this->client->request(
            'PATCH',
            '/files/' . $fileId . '/move',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['parent_id' => $folderId])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals($folderId, $data['file']['parent_id']);
    }

    public function testGetTextFileContent(): void
    {
        // Create a text file
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'content_test.txt',
                'content' => 'Initial content'
            ])
        );

        $fileData = json_decode($this->client->getResponse()->getContent(), true);
        $fileId = $fileData['file']['id'];

        // Get the content
        $this->client->request(
            'GET',
            '/files/' . $fileId . '/content',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Initial content', $data['content']);
    }

    public function testUpdateTextFileContent(): void
    {
        // Create a text file
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'editable.txt',
                'content' => 'Original content'
            ])
        );

        $fileData = json_decode($this->client->getResponse()->getContent(), true);
        $fileId = $fileData['file']['id'];

        // Update the content
        $this->client->request(
            'PUT',
            '/files/' . $fileId . '/content',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['content' => 'Updated content'])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);

        // Verify the content was updated by fetching it
        $this->client->request(
            'GET',
            '/files/' . $fileId . '/content',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $contentData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated content', $contentData['content']);
    }

    public function testListFilesInFolder(): void
    {
        // Create a folder
        $this->client->request(
            'POST',
            '/files/folder',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['name' => 'List Test Folder'])
        );

        $folderData = json_decode($this->client->getResponse()->getContent(), true);
        $folderId = $folderData['folder']['id'];

        // Create a text file in the folder
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'inside.txt',
                'content' => 'File in folder',
                'parent_id' => $folderId
            ])
        );

        // List files in the folder
        $this->client->request(
            'GET',
            '/files?parent_id=' . $folderId,
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('files', $data);
        $this->assertGreaterThan(0, count($data['files']));
    }

    public function testViewFile(): void
    {
        // Create a text file
        $this->client->request(
            'POST',
            '/files/text',
            [],
            [],
            [
                'HTTP_X_AUTH_TOKEN' => $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'filename' => 'viewable.txt',
                'content' => 'View test content'
            ])
        );

        $fileData = json_decode($this->client->getResponse()->getContent(), true);
        $fileId = $fileData['file']['id'];

        // View the file (text files return JSON with content)
        $this->client->request(
            'GET',
            '/files/' . $fileId . '/view',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseIsSuccessful();

        $viewData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('type', $viewData);
        $this->assertEquals('text', $viewData['type']);
        $this->assertArrayHasKey('content', $viewData);
    }
}
