<?php

namespace classes\system;

final class CsrfService {

    private const STORAGE_KEY = 'csrf_base_token';
    private const PARAM_NAME = 'csrf';

    public static function appendToUrl(string $url): string {
        $context = self::normalizeUrlContext($url);
        $token = self::buildTokenForContext($context);

        $fragment = '';
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . self::PARAM_NAME . '=' . rawurlencode($token) . $fragment;
    }

    public static function tokenForUrl(string $url): string {
        return self::buildTokenForContext(self::normalizeUrlContext($url));
    }

    public static function isValidForCurrentRequest(): bool {
        $token = (string) ($_REQUEST[self::PARAM_NAME] ?? '');
        if ($token === '') {
            return false;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return self::isValidForUrl($requestUri, $token);
    }

    public static function isValidForUrl(string $url, string $providedToken): bool {
        $providedToken = trim($providedToken);
        if ($providedToken === '') {
            return false;
        }

        $context = self::normalizeUrlContext($url);
        $expectedToken = self::buildTokenForContext($context);

        return hash_equals($expectedToken, $providedToken);
    }

    public static function getParameterName(): string {
        return self::PARAM_NAME;
    }

    private static function buildTokenForContext(string $context): string {
        return hash_hmac('sha256', $context, self::getBaseToken());
    }

    private static function getBaseToken(): string {
        $token = trim((string) Session::get(self::STORAGE_KEY));
        if ($token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        Session::set(self::STORAGE_KEY, $token);
        return $token;
    }

    private static function normalizeUrlContext(string $url): string {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = $path !== '' ? $path : '/';

        $query = [];
        $rawQuery = (string) parse_url($url, PHP_URL_QUERY);
        if ($rawQuery !== '') {
            parse_str($rawQuery, $query);
        }

        unset($query[self::PARAM_NAME]);
        $query = self::sortRecursive($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $queryString !== '' ? ($path . '?' . $queryString) : $path;
    }

    private static function sortRecursive(array $data): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursive($value);
            }
        }

        ksort($data);
        return $data;
    }
}
