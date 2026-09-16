<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiAuthControllerTest extends WebTestCase
{
    public function testLoginSuccessful(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@test.com',
            'password' => 'Testtest1',
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('test@test.com', $response['user']['email']);

        // Test authenticated /api/me with Bearer token
        $token = $response['token'];
        $client->request('GET', '/api/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $me = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('test@test.com', $me['email']);
    }

    public function testLoginInvalidPassword(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@test.com',
            'password' => 'WrongPassword',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }
}
