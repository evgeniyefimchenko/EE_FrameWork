<?php

namespace classes\plugins;

use classes\system\SysClass;

/**
 * Класс взаимодействия с внешними сервисами по средством запросов
 * Реализован по правилам REST API
 */
class HTTPRequester {

    /**
     * Выполняет HTTP-GET запрос
     * @param string $url URL для запроса
     * @param array $params Параметры запроса
     * @param array $header Заголовки запроса
     * @return string Ответ сервера или пустая строка, если запрос не удался или пуст
     * @throws Exception В случае ошибки выполнения запроса
     */
    public static function HTTPGet(string $url, array $params, array $header = []): string {
        $query = http_build_query($params);
        $urlWithParams = $url . '?' . $query;
        return HTTPRequester::HTTPRequest($urlWithParams, 'GET', null, $header);
    }

    /**
     * @description Make HTTP-POST call
     * @param string $url URL для запроса
     * @param array $params Параметры запроса
     * @param bool $json Отправлять параметры в формате JSON
     * @param array $header Заголовки запроса
     * @return string HTTP-Response body or an empty string if the request fails or is empty
     * @throws Exception В случае ошибки выполнения запроса
     */
    public static function HTTPPost(string $url, array $params = [], bool $json = false, array $header = []): string {
        $query = $json ? json_encode($params) : http_build_query($params);
        $header = $json ? array_merge(['Content-Type: application/json'], $header) : $header;
        return HTTPRequester::HTTPRequest($url, 'POST', $query, $header);
    }

    /**
     * Выполняет HTTP-PUT запрос
     * @param string $url URL для запроса
     * @param array $params Параметры запроса
     * @param bool $json Отправлять данные в формате JSON
     * @param array $header Заголовки запроса
     * @return string Ответ сервера или пустая строка, если запрос не удался или пуст
     * @throws Exception В случае ошибки выполнения запроса
     */
    public static function HTTPPut(string $url, array $params = [], bool $json = false, array $header = []): string {
        $query = $json ? json_encode($params) : http_build_query($params);
        return HTTPRequester::HTTPRequest($url, 'PUT', $query, $header);
    }

    /**
     * Выполняет HTTP-DELETE запрос
     * @param string $url URL для запроса
     * @param array $params Параметры запроса
     * @param bool $json Отправлять данные в формате JSON
     * @param array $header Заголовки запроса
     * @return string Ответ сервера или пустая строка, если запрос не удался или пуст
     * @throws Exception В случае ошибки выполнения запроса
     */
    public static function HTTPDelete(string $url, array $params = [], bool $json = false, array $header = []): string {
        $query = $json ? json_encode($params) : http_build_query($params);
        return HTTPRequester::HTTPRequest($url, 'DELETE', $query, $header);
    }

    /**
     * Отправляет сформированный запрос на удаленный сервер.
     * @param string $url Полный адрес запроса.
     * @param string $method Метод REST API.
     * @param string|array $params Строка или массив с параметрами.
     * @param array $header Заголовки запроса.
     * @return string Ответ сервера.
     * @throws Exception В случае ошибки выполнения запроса.
     */
    public static function HTTPRequest(string $url, string $method = 'POST', $params = [], array $header = []): string {
        if (function_exists('curl_init')) {
            return self::curlRequest($url, $method, $params, $header);
        } else {
            return self::streamContextRequest($url, $method, $params, $header);
        }
    }

    /**
     * Выполняет запрос через cURL.
     */
    private static function curlRequest(string $url, string $method, $params, array $header): string {
        if (!count($header)) {
            $header = ['Content-Type: application/json'];
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true); // Включить проверку SSL
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
        }
        if (!is_string($params)) {
            $params = http_build_query($params);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }
        curl_close($curl);
        return $response;
    }

    /**
     * Выполняет запрос через контекст потока PHP.
     */
    private static function streamContextRequest(string $url, string $method, $params, array $header): string {
        if (!is_string($params)) {
            $params = http_build_query($params);
        }
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $header),
                'content' => $params,
                'ignore_errors' => true // Для получения содержимого, даже если возникла ошибка
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        $context = stream_context_create($contextOptions);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Ошибка выполнения запроса к $url");
        }
        return $response;
    }

}
