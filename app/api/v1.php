<?php

use classes\system\ApiKeyService;
use classes\system\Constants;
use classes\system\ContentApiService;
use classes\system\ControllerBase;
use classes\system\Logger;
use classes\system\SysClass;

final class ControllerV1 extends ControllerBase {

    private const AUTH_WINDOW_SECONDS = 60;
    private const AUTH_MAX_ATTEMPTS = 30;
    private const READ_WINDOW_SECONDS = 60;
    private const READ_MAX_REQUESTS = 120;
    private const WRITE_WINDOW_SECONDS = 60;
    private const WRITE_MAX_REQUESTS = 30;

    private ?array $apiAuth = null;
    private ?ContentApiService $contentApi = null;

    public function index($params = []): void {
        unset($params);
        $this->respondJson([
            'success' => true,
            'version' => 'v1',
            'endpoints' => [
                'GET /api/v1/pages/id/{id}',
                'POST /api/v1/pages',
                'PUT /api/v1/pages/id/{id}',
                'PATCH /api/v1/pages/id/{id}',
                'GET /api/v1/categories/id/{id}',
                'POST /api/v1/categories',
                'PUT /api/v1/categories/id/{id}',
                'PATCH /api/v1/categories/id/{id}',
            ],
            'auth' => [
                'header' => 'Authorization: Bearer {api_key}',
                'alt_header' => 'X-API-Key: {api_key}',
                'role' => 'admin',
            ],
        ]);
    }

    public function pages($params = []): void {
        $this->handleEntityRequest('page', (array) $params);
    }

    public function categories($params = []): void {
        $this->handleEntityRequest('category', (array) $params);
    }

    private function handleEntityRequest(string $entityType, array $params): void {
        $auth = $this->requireApiAdmin();
        if (!is_array($auth)) {
            return;
        }

        $service = $this->getContentApi();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->enforceRateLimit($auth, $method);
        $entityId = $this->extractEntityId($params);
        $payload = $this->readJsonPayload();

        if ($method === 'GET') {
            if ($entityId <= 0) {
                $this->respondJsonError('Entity ID is required.', 'missing_entity_id', 422);
                return;
            }
            $result = $service->getEntity($entityType, $entityId, (string) ($_GET['language_code'] ?? ''));
            if ($result->isSuccess()) {
                $this->logApiAudit('entity_read', $auth, $entityType, $method, $entityId, [
                    'language_code' => (string) ($_GET['language_code'] ?? ''),
                ]);
            }
            $this->respondOperationResult($result, 200, 404);
            return;
        }

        if ($method === 'POST') {
            $result = $service->createEntity($entityType, $payload);
            if ($result->isSuccess()) {
                $createdId = (int) ($result->getData()['entity_id'] ?? $result->getData()['id'] ?? 0);
                $this->logApiAudit('entity_created', $auth, $entityType, $method, $createdId, [
                    'payload_keys' => array_keys($payload),
                ]);
            }
            $this->respondOperationResult($result, 201, 422);
            return;
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($entityId <= 0) {
                $this->respondJsonError('Entity ID is required.', 'missing_entity_id', 422);
                return;
            }
            $result = $service->updateEntity($entityType, $entityId, $payload);
            if ($result->isSuccess()) {
                $this->logApiAudit('entity_updated', $auth, $entityType, $method, $entityId, [
                    'payload_keys' => array_keys($payload),
                ]);
            }
            $this->respondOperationResult($result, 200, 422);
            return;
        }

        $this->respondJsonError('HTTP method is not supported.', 'method_not_allowed', 405, [
            'allowed' => ['GET', 'POST', 'PUT', 'PATCH'],
        ]);
    }

    private function requireApiAdmin(): ?array {
        if (is_array($this->apiAuth)) {
            return $this->apiAuth;
        }

        ApiKeyService::ensureInfrastructure();
        $rawKey = ApiKeyService::extractRequestApiKey();
        if ($rawKey === '') {
            $this->enforceAnonymousAuthRateLimit('missing_key');
            $this->respondJsonError('API key is required.', 'api_key_required', 401);
            return null;
        }

        $resolved = ApiKeyService::resolveActiveKey($rawKey);
        if (!is_array($resolved)) {
            $this->enforceAnonymousAuthRateLimit('invalid_key');
            $this->respondJsonError('API key is invalid.', 'api_key_invalid', 401);
            return null;
        }

        if ((int) ($resolved['active'] ?? 0) !== 2) {
            $this->respondJsonError('User is inactive.', 'api_user_inactive', 403);
            return null;
        }

        if ((int) ($resolved['user_role'] ?? 0) !== Constants::ADMIN) {
            $this->respondJsonError('API is available only for administrators.', 'api_role_forbidden', 403);
            return null;
        }

        ApiKeyService::touchKeyUsage((int) ($resolved['api_key_id'] ?? 0), (string) SysClass::getClientIp());
        $this->apiAuth = $resolved;

        return $this->apiAuth;
    }

    private function enforceAnonymousAuthRateLimit(string $reason): void {
        $ip = (string) SysClass::getClientIp();
        $rateLimitState = $this->consumeRateLimitBucket(
            'api_auth:' . hash('sha256', $ip . ':' . $reason),
            self::AUTH_WINDOW_SECONDS,
            self::AUTH_MAX_ATTEMPTS
        );
        if ($rateLimitState['allowed']) {
            return;
        }

        Logger::warning('content_api', 'API auth rate limit exceeded.', [
            'reason' => $reason,
            'request_ip' => $ip,
        ]);
        $this->respondJsonError(
            'Too many authentication attempts. Please retry later.',
            'api_auth_rate_limited',
            429,
            ['retry_after' => $rateLimitState['retry_after']]
        );
    }

    private function enforceRateLimit(array $auth, string $method): void {
        $method = strtoupper($method);
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $windowSeconds = $isWrite ? self::WRITE_WINDOW_SECONDS : self::READ_WINDOW_SECONDS;
        $maxRequests = $isWrite ? self::WRITE_MAX_REQUESTS : self::READ_MAX_REQUESTS;
        $apiKeyId = (int) ($auth['api_key_id'] ?? 0);
        $userId = (int) ($auth['user_id'] ?? 0);
        $ip = (string) SysClass::getClientIp();

        $rateLimitState = $this->consumeRateLimitBucket(
            'content_api:' . hash('sha256', $apiKeyId . ':' . $userId . ':' . $ip . ':' . ($isWrite ? 'write' : 'read')),
            $windowSeconds,
            $maxRequests
        );
        if ($rateLimitState['allowed']) {
            return;
        }

        Logger::warning('content_api', 'Content API rate limit exceeded.', [
            'api_key_id' => $apiKeyId,
            'user_id' => $userId,
            'request_ip' => $ip,
            'method' => $method,
        ]);
        $this->respondJsonError(
            'Rate limit exceeded. Please retry later.',
            'api_rate_limited',
            429,
            ['retry_after' => $rateLimitState['retry_after']]
        );
    }

    /**
     * @return array{allowed:bool,retry_after:int}
     */
    private function consumeRateLimitBucket(string $bucketKey, int $windowSeconds, int $maxRequests): array {
        $bucketHash = hash('sha256', $bucketKey);
        $bucketPath = rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP . 'api_rate_limit' . ENV_DIRSEP . substr($bucketHash, 0, 2) . ENV_DIRSEP . $bucketHash . '.json';
        SysClass::createDirectoriesForFile($bucketPath);

        $handle = @fopen($bucketPath, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $now = time();
        $allowed = true;
        $retryAfter = 0;

        try {
            if (!@flock($handle, LOCK_EX)) {
                return ['allowed' => true, 'retry_after' => 0];
            }

            $rawState = stream_get_contents($handle);
            $state = is_string($rawState) && trim($rawState) !== ''
                ? json_decode($rawState, true)
                : [];
            if (!is_array($state)) {
                $state = [];
            }

            $windowStart = (int) ($state['window_start'] ?? 0);
            $count = (int) ($state['count'] ?? 0);
            if ($windowStart <= 0 || ($now - $windowStart) >= $windowSeconds) {
                $windowStart = $now;
                $count = 0;
            }

            if ($count >= $maxRequests) {
                $allowed = false;
                $retryAfter = max(1, $windowSeconds - ($now - $windowStart));
            } else {
                $count++;
                $state = [
                    'window_start' => $windowStart,
                    'count' => $count,
                ];
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }

        return [
            'allowed' => $allowed,
            'retry_after' => $retryAfter,
        ];
    }

    private function logApiAudit(string $event, array $auth, string $entityType, string $method, int $entityId = 0, array $extraContext = []): void {
        Logger::audit('content_api', 'Content API request completed.', array_merge([
            'event' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'method' => strtoupper($method),
            'api_key_id' => (int) ($auth['api_key_id'] ?? 0),
            'user_id' => (int) ($auth['user_id'] ?? 0),
            'request_ip' => (string) SysClass::getClientIp(),
        ], $extraContext));
    }

    private function getContentApi(): ContentApiService {
        if (!$this->contentApi instanceof ContentApiService) {
            $this->contentApi = new ContentApiService();
        }

        return $this->contentApi;
    }

    private function readJsonPayload(): array {
        $raw = trim((string) file_get_contents('php://input'));
        if ($raw === '') {
            return $_POST ? (array) $_POST : [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->respondJsonError('JSON payload is invalid.', 'invalid_json_payload', 400);
            exit;
        }

        return $decoded;
    }

    private function extractEntityId(array $params): int {
        if (in_array('id', $params, true)) {
            $key = array_search('id', $params, true);
            if ($key !== false && isset($params[$key + 1]) && is_numeric($params[$key + 1])) {
                return (int) $params[$key + 1];
            }
        }

        foreach ($params as $param) {
            if (is_numeric($param)) {
                return (int) $param;
            }
        }

        $candidate = $_GET['id'] ?? '';
        return is_numeric($candidate) ? (int) $candidate : 0;
    }

    private function respondOperationResult(\classes\system\OperationResult $result, int $successStatus = 200, int $failureStatus = 422): void {
        if ($result->isSuccess()) {
            $this->respondJson([
                'success' => true,
                'data' => $result->getData(),
                'message' => $result->getMessage(''),
                'code' => $result->getCode(),
            ], $successStatus);
            return;
        }

        $status = $result->getCode() === 'entity_not_found' ? 404 : $failureStatus;
        $this->respondJson([
            'success' => false,
            'error' => $result->getMessage('Operation failed.'),
            'code' => $result->getCode(),
            'context' => $result->getContext(),
        ], $status);
    }

    private function respondJsonError(string $message, string $code, int $status, array $extra = []): void {
        $this->respondJson(array_merge([
            'success' => false,
            'error' => $message,
            'code' => $code,
        ], $extra), $status);
    }

    private function respondJson(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
