<?php

namespace classes\system;

interface AuthProviderInterface {

    public function getProviderKey(): string;

    public function isConfigured(): bool;

    public function startAuth(array $context = []): string;

    public function handleCallback(array $params = []): array;

    public function fetchProfile(string $accessToken): array;

    public function normalizeIdentity(array $profile): array;
}
