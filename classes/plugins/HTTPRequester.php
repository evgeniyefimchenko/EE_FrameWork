<?php

namespace classes\system;

use classes\system\SysClass;

/**
 * Класс взаимодействия с внешними сервисами по средством Curl
 * Реализован по правилам REST API
 */
class HTTPRequester {

    /**
     * @description Make HTTP-GET call
     * @param       $url
     * @param       array $params
     * @return      HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPGet($url, array $params, $header = []) {
        $query = http_build_query($params);
        return HTTPRequester::HTTPRequest($url . '?' . $query, 'GET', $header);
    }

    /**
     * @description Make HTTP-POST call
     * @param       $url
     * @param       array $params
     * @return      HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPPost($url, $params = [], $json = false, $header = []) {
        if (!$json) {
            $query = http_build_query($params);
        } else {
            $query = json_encode($params);
        }
        return HTTPRequester::HTTPRequest($url, 'POST', $query, $header);
    }

    /**
     * @description Make HTTP-PUT call
     * @param       $url
     * @param       array $params
     * @return      HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPPut($url, $params = [], $json = false, $header = []) {
        if (!$json) {
            $query = http_build_query($params);
        } else {
            $query = json_encode($params);
        }
        return HTTPRequester::HTTPRequest($url, 'PUT', $query, $header);
    }

    /**
     * @category Make HTTP-DELETE call
     * @param    $url
     * @param    array $params
     * @return   HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPDelete($url, $params = [], $json = false, $header = []) {
        if (!$json) {
            $query = http_build_query($params);
        } else {
            $query = json_encode($params);
        }
        return HTTPRequester::HTTPRequest($url, 'DELETE', $query, $header);
    }

    /**
     * Отправляет сформированный запрос на удаленный сервер.
     * @param string $url Полный адрес запроса.
     * @param string $method Метод REST API.
     * @param string|array $params Строка или массив с параметрами.
     * @param array $header Заголовки запроса.
     * @return string Ответ сервера.
     * @throws Exception В случае ошибки cURL.
     */
    public static function HTTPRequest(string $url, string $method = 'POST', $params = [], array $header = []): string {
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
}
