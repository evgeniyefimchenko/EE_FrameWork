<?php

use classes\system\ApiKeyService;
use classes\system\Constants;
use classes\system\ContentApiService;
use classes\system\ControllerBase;

final class ControllerV1 extends ControllerBase {

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
        $entityId = $this->extractEntityId($params);
        $payload = $this->readJsonPayload();

        if ($method === 'GET') {
            if ($entityId <= 0) {
                $this->respondJsonError('Entity ID is required.', 'missing_entity_id', 422);
                return;
            }
            $result = $service->getEntity($entityType, $entityId, (string) ($_GET['language_code'] ?? ''));
            $this->respondOperationResult($result, 200, 404);
            return;
        }

        if ($method === 'POST') {
            $result = $service->createEntity($entityType, $payload);
            $this->respondOperationResult($result, 201, 422);
            return;
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($entityId <= 0) {
                $this->respondJsonError('Entity ID is required.', 'missing_entity_id', 422);
                return;
            }
            $result = $service->updateEntity($entityType, $entityId, $payload);
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
            $this->respondJsonError('API key is required.', 'api_key_required', 401);
            return null;
        }

        $resolved = ApiKeyService::resolveActiveKey($rawKey);
        if (!is_array($resolved)) {
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

        ApiKeyService::touchKeyUsage((int) ($resolved['api_key_id'] ?? 0), (string) \classes\system\SysClass::getClientIp());
        $this->apiAuth = $resolved;

        return $this->apiAuth;
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
