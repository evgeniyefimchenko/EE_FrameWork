<?php

namespace classes\system;

use classes\plugins\HTTPRequester;

class GoogleAuthProvider implements AuthProviderInterface {

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const PROFILE_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function getProviderKey(): string {
        return 'google';
    }

    public function isConfigured(): bool {
        return trim((string) ENV_AUTH_GOOGLE_CLIENT_ID) !== '' && trim((string) ENV_AUTH_GOOGLE_CLIENT_SECRET) !== '';
    }

    public function startAuth(array $context = []): string {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google auth provider is not configured');
        }

        $params = [
            'client_id' => (string) ENV_AUTH_GOOGLE_CLIENT_ID,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
            'state' => (string) ($context['state'] ?? ''),
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function handleCallback(array $params = []): array {
        $code = trim((string) ($params['code'] ?? ''));
        if ($code === '') {
            throw new \InvalidArgumentException('Google callback code is empty');
        }

        $tokenData = $this->exchangeCode($code);
        $profile = $this->fetchProfile((string) ($tokenData['access_token'] ?? ''));
        $normalized = $this->normalizeIdentity($profile);
        $normalized['access_token'] = (string) ($tokenData['access_token'] ?? '');
        $normalized['raw_profile'] = $profile;

        return $normalized;
    }

    public function fetchProfile(string $accessToken): array {
        if ($accessToken === '') {
            throw new \InvalidArgumentException('Google access token is empty');
        }

        $response = HTTPRequester::HTTPRequest(
            self::PROFILE_URL,
            'GET',
            '',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );
        $profile = json_decode((string) $response, true);
        if (!is_array($profile) || !isset($profile['sub'])) {
            throw new \RuntimeException('Failed to fetch Google profile');
        }

        return $profile;
    }

    public function normalizeIdentity(array $profile): array {
        $email = trim((string) ($profile['email'] ?? ''));
        return [
            'provider' => $this->getProviderKey(),
            'provider_user_id' => trim((string) ($profile['sub'] ?? '')),
            'provider_email' => $email,
            'provider_email_verified' => !empty($profile['email_verified']) ? 1 : 0,
            'name' => trim((string) ($profile['name'] ?? $email)),
            'avatar' => trim((string) ($profile['picture'] ?? '')),
        ];
    }

    private function exchangeCode(string $code): array {
        $response = HTTPRequester::HTTPPost(
            self::TOKEN_URL,
            [
                'code' => $code,
                'client_id' => (string) ENV_AUTH_GOOGLE_CLIENT_ID,
                'client_secret' => (string) ENV_AUTH_GOOGLE_CLIENT_SECRET,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
            ],
            false,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]
        );
        $tokenData = json_decode((string) $response, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            throw new \RuntimeException('Failed to exchange Google auth code');
        }

        return $tokenData;
    }

    private function getRedirectUri(): string {
        $configured = trim((string) ENV_AUTH_GOOGLE_REDIRECT_URI);
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) ENV_URL_SITE, '/') . '/auth/google/callback';
    }
}
