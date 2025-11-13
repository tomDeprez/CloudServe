<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRegister(): void
    {
        $this->client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => 'password123'
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('id', $data['user']);
    }

    public function testRegisterWithExistingEmail(): void
    {
        $email = 'duplicate_' . uniqid() . '@example.com';

        // Première inscription
        $this->client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123'
            ])
        );

        $this->assertResponseIsSuccessful();

        // Seconde inscription avec le même email
        $this->client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123'
            ])
        );

        $this->assertResponseStatusCodeSame(409);
    }

    public function testLogin(): void
    {
        $email = 'login_test_' . uniqid() . '@example.com';
        $password = 'password123';

        // Créer un utilisateur
        $this->client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        // Se connecter
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($email, $data['user']['email']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeEndpoint(): void
    {
        $email = 'me_test_' . uniqid() . '@example.com';
        $password = 'password123';

        // Créer et se connecter
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

        $loginData = json_decode($this->client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Appeler /me avec le token
        $this->client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_X_AUTH_TOKEN' => $token]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($email, $data['email']);
        $this->assertArrayHasKey('quota', $data);
        $this->assertArrayHasKey('usedSpace', $data);
    }

    public function testMeEndpointWithoutToken(): void
    {
        $this->client->request('GET', '/me');
        $this->assertResponseStatusCodeSame(401);
    }
}
