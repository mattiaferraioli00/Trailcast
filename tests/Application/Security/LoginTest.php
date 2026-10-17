<?php

namespace App\Tests\Application\Security;

use App\Tests\Application\ApplicationTestCase;
use PHPUnit\Framework\Attributes\Test;

class LoginTest extends ApplicationTestCase
{
    /**
     * Boots the application with a staff and a customer user in the database.
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->prepareApplication();
        $this->createUser('staff@trailcast.test', ['ROLE_STAFF'], 'staff-secret');
        $this->createUser('customer@trailcast.test', [], 'customer-secret');
    }

    /**
     * Given a user with valid credentials,
     * when POST /login is called,
     * then the response is 200 with a JWT.
     */
    #[Test]
    public function responds_with_200_and_a_token_for_valid_credentials(): void
    {
        $this->callApi('POST', '/login', ['email' => 'staff@trailcast.test', 'password' => 'staff-secret']);

        $this->assertResponseStatusCodeSame(200);

        $body = $this->responseBody();
        $this->assertSame(['token'], array_keys($body));
        $this->assertMatchesRegularExpression('/^[\w-]+\.[\w-]+\.[\w-]+$/', $body['token']);
    }

    /**
     * Given a wrong password or an unknown email,
     * when POST /login is called,
     * then the response is 401 and no token is issued.
     */
    #[Test]
    public function responds_with_401_for_wrong_credentials(): void
    {
        foreach ([
            ['email' => 'staff@trailcast.test', 'password' => 'wrong'],
            ['email' => 'nobody@trailcast.test', 'password' => 'staff-secret'],
        ] as $credentials) {
            $this->callApi('POST', '/login', $credentials);

            $this->assertResponseStatusCodeSame(401);
            $this->assertArrayNotHasKey('token', $this->responseBody());
        }
    }

    /**
     * Given the token of a staff user obtained from the login,
     * when it is sent to the staff-only endpoint,
     * then the request is authorized.
     */
    #[Test]
    public function issues_a_token_that_authorizes_staff_endpoints(): void
    {
        $this->callApi('POST', '/login', ['email' => 'staff@trailcast.test', 'password' => 'staff-secret']);
        $token = $this->responseBody()['token'];

        $this->client->request('POST', '/excursions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'title' => 'Etna sunrise',
            'description' => 'Guided trek to the summit craters.',
            'city' => 'Catania',
            'country' => 'IT',
            'date' => (new \DateTime('+7 days'))->format('Y-m-d'),
            'capacity' => 10,
        ]));

        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * Given the token of a user without ROLE_STAFF obtained from the login,
     * when it is sent to the staff-only endpoint,
     * then the request is forbidden: the role comes from the stored user.
     */
    #[Test]
    public function issues_a_token_that_does_not_authorize_staff_endpoints_to_customers(): void
    {
        $this->callApi('POST', '/login', ['email' => 'customer@trailcast.test', 'password' => 'customer-secret']);
        $token = $this->responseBody()['token'];

        $this->client->request('POST', '/excursions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Given a token whose signature was tampered with,
     * when it is sent to a protected endpoint,
     * then the response is 401.
     */
    #[Test]
    public function rejects_a_tampered_token(): void
    {
        $this->callApi('POST', '/login', ['email' => 'staff@trailcast.test', 'password' => 'staff-secret']);
        $token = $this->responseBody()['token'] . 'x';

        $this->client->request('POST', '/excursions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(401);
    }
}
