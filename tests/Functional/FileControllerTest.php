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
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test file content');

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
            ['file' => $uploadedFile],
            ['HTTP_X_AUTH_TOKEN' => $this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('file', $data);
        $this->assertEquals('test.txt', $data['file']['filename']);
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
}
