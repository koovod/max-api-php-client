<?php

declare(strict_types=1);

namespace MaxApi\Tests;

use MaxApi\MaxApiClient;
use MaxApi\MaxApiException;
use PHPUnit\Framework\TestCase;

final class MaxApiClientTest extends TestCase
{
    public function testGetMeAddsAuthorizationHeader(): void
    {
        $capturedHeaders = [];
        $client = $this->makeClient(
            function (string $method, string $url, array $headers) use (&$capturedHeaders): array {
                $capturedHeaders = $headers;
                return ['status' => 200, 'body' => '{}'];
            }
        );

        $client->getMe();

        $this->assertContains('Authorization: token', $capturedHeaders);
        $this->assertContains('Accept: application/json', $capturedHeaders);
    }

    public function testSendMessageEncodesPayload(): void
    {
        $captured = null;
        $client = $this->makeClient(
            function (string $method, string $url, array $headers, ?string $body) use (&$captured): array {
                $captured = [$method, $url, $headers, $body];
                return ['status' => 200, 'body' => '{"message_id":"1"}'];
            }
        );

        $result = $client->sendMessage(['chat_id' => 1, 'text' => 'Hello']);

        $this->assertSame('POST', $captured[0]);
        $this->assertSame('https://example.com/messages', $captured[1]);
        $this->assertSame('{"message_id":"1"}', json_encode($result));
        $this->assertSame(
            json_encode(['chat_id' => 1, 'text' => 'Hello']),
            $captured[3]
        );
    }

    public function testErrorResponseThrowsException(): void
    {
        $client = $this->makeClient(
            fn () => ['status' => 400, 'body' => '{"message":"Invalid"}']
        );

        $this->expectException(MaxApiException::class);
        $this->expectExceptionMessage('Invalid');
        $client->getChats();
    }

    public function testPinnedMessageCanReturnNull(): void
    {
        $client = $this->makeClient(
            fn () => ['status' => 200, 'body' => 'null']
        );

        $this->assertNull($client->getPinnedMessage(1));
    }

    private function makeClient(callable $handler): MaxApiClient
    {
        return new MaxApiClient('token', [
            'http_handler' => $handler,
            'base_uri' => 'https://example.com',
        ]);
    }
}
