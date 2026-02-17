<?php

declare(strict_types=1);

namespace MaxApi;

use Closure;
use JsonException;

final class MaxApiClient
{
    public const DEFAULT_BASE_URI = 'https://platform-api.max.ru';

    private string $accessToken;
    private string $baseUri;
    private int $timeout;
    private string $userAgent;
    /**
     * @var Closure|null
     */
    private $httpHandler;

    /**
     * @param array{base_uri?:string,timeout?:int,user_agent?:string,http_handler?:callable} $options
     */
    public function __construct(string $accessToken, array $options = [])
    {
        $this->accessToken = $accessToken;
        $this->baseUri = rtrim($options['base_uri'] ?? self::DEFAULT_BASE_URI, '/');
        $this->timeout = (int)($options['timeout'] ?? 30);
        $this->userAgent = $options['user_agent'] ?? 'max-api-php-client/1.0';
        $handler = $options['http_handler'] ?? null;
        $this->httpHandler = $handler instanceof Closure ? $handler : ($handler === null ? null : Closure::fromCallable($handler));
    }

    public function getMe(): array
    {
        return $this->request('GET', '/me');
    }

    public function getChats(array $params = []): array
    {
        return $this->request('GET', '/chats', $params);
    }

    public function getChat(int $chatId): array
    {
        return $this->request('GET', "/chats/{$chatId}");
    }

    public function updateChat(int $chatId, array $payload): array
    {
        return $this->request('PATCH', "/chats/{$chatId}", [], $payload);
    }

    public function deleteChat(int $chatId): array
    {
        return $this->request('DELETE', "/chats/{$chatId}");
    }

    public function sendChatAction(int $chatId, string $action, array $extra = []): array
    {
        return $this->request('POST', "/chats/{$chatId}/actions", [], array_merge(['action' => $action], $extra));
    }

    public function getPinnedMessage(int $chatId)
    {
        return $this->request('GET', "/chats/{$chatId}/pin");
    }

    public function pinMessage(int $chatId, array $payload): array
    {
        return $this->request('PUT', "/chats/{$chatId}/pin", [], $payload);
    }

    public function unpinMessage(int $chatId): array
    {
        return $this->request('DELETE', "/chats/{$chatId}/pin");
    }

    public function getBotMembership(int $chatId): array
    {
        return $this->request('GET', "/chats/{$chatId}/members/me");
    }

    public function leaveChat(int $chatId): array
    {
        return $this->request('DELETE', "/chats/{$chatId}/members/me");
    }

    public function getChatAdmins(int $chatId): array
    {
        return $this->request('GET', "/chats/{$chatId}/members/admins");
    }

    public function assignChatAdmins(int $chatId, array $payload): array
    {
        return $this->request('POST', "/chats/{$chatId}/members/admins", [], $payload);
    }

    public function removeChatAdmin(int $chatId, int $userId): array
    {
        return $this->request('DELETE', "/chats/{$chatId}/members/admins/{$userId}");
    }

    public function getChatMembers(int $chatId, array $params = []): array
    {
        return $this->request('GET', "/chats/{$chatId}/members", $params);
    }

    public function addChatMembers(int $chatId, array $payload): array
    {
        return $this->request('POST', "/chats/{$chatId}/members", [], $payload);
    }

    public function removeChatMembers(int $chatId, array $payload): array
    {
        return $this->request('DELETE', "/chats/{$chatId}/members", [], $payload);
    }

    public function getSubscriptions(): array
    {
        return $this->request('GET', '/subscriptions');
    }

    public function createSubscription(array $payload): array
    {
        return $this->request('POST', '/subscriptions', [], $payload);
    }

    public function deleteSubscription(array $payload): array
    {
        return $this->request('DELETE', '/subscriptions', [], $payload);
    }

    public function getUpdates(array $params = []): array
    {
        return $this->request('GET', '/updates', $params);
    }

    public function createUpload(array $payload): array
    {
        return $this->request('POST', '/uploads', [], $payload);
    }

    public function getMessages(array $params = []): array
    {
        return $this->request('GET', '/messages', $params);
    }

    public function sendMessage(array $payload): array
    {
        return $this->request('POST', '/messages', [], $payload);
    }

    public function editMessage(array $payload): array
    {
        return $this->request('PUT', '/messages', [], $payload);
    }

    public function deleteMessage(array $payload): array
    {
        return $this->request('DELETE', '/messages', [], $payload);
    }

    public function getMessage(string $messageId): array
    {
        return $this->request('GET', "/messages/{$messageId}");
    }

    public function getVideo(string $videoToken): array
    {
        return $this->request('GET', "/videos/{$videoToken}");
    }

    public function answerCallback(array $payload): array
    {
        return $this->request('POST', '/answers', [], $payload);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null, array $headers = [])
    {
        $url = $this->baseUri . $path;
        if ($query !== []) {
            $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($queryString !== '') {
                $url .= '?' . $queryString;
            }
        }

        $compiledHeaders = $this->compileHeaders($headers, $body !== null);
        $payload = null;

        if ($body !== null) {
            try {
                $payload = json_encode($body, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new MaxApiException('Unable to encode request body: ' . $exception->getMessage());
            }
        }

        $response = $this->dispatch($method, $url, $compiledHeaders, $payload);

        $status = $response['status'] ?? 0;
        $rawBody = $response['body'] ?? '';

        if ($status >= 400) {
            $decoded = $this->decodeBody($rawBody);
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string)$decoded['message']
                : 'MAX API error';
            throw new MaxApiException($message, $status, is_array($decoded) ? $decoded : []);
        }

        return $this->decodeBody($rawBody);
    }

    private function compileHeaders(array $headers, bool $hasBody): array
    {
        $compiled = [
            'Authorization: ' . $this->accessToken,
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent,
        ];

        if ($hasBody) {
            $compiled[] = 'Content-Type: application/json';
        }

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $compiled[] = $value;
            } else {
                $compiled[] = $key . ': ' . $value;
            }
        }

        return $compiled;
    }

    /**
     * @return array{status:int,body:string}
     */
    private function dispatch(string $method, string $url, array $headers, ?string $payload): array
    {
        if ($this->httpHandler !== null) {
            $result = ($this->httpHandler)($method, $url, $headers, $payload);
            return [
                'status' => (int)($result['status'] ?? 0),
                'body' => (string)($result['body'] ?? ''),
            ];
        }

        if (!function_exists('curl_init')) {
            throw new MaxApiException('ext-curl is required when no custom http_handler is provided.');
        }

        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle);
            $code = curl_errno($handle);
            curl_close($handle);
            throw new MaxApiException('cURL error: ' . $message, $code);
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE) ?: 0;
        curl_close($handle);

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeBody(string $rawBody)
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MaxApiException('Unable to decode response: ' . $exception->getMessage());
        }

        return $decoded;
    }
}
