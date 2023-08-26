<?php

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
     * Отправит сформированный запрос на удалённый сервер
     * @param str $url - полный адрес запроса
     * @param str $method - метод REST API
     * @param str $params - строка с параметрами
     * @param array $header - заголовок
     * @return values - ответ сервера
     */
    public static function HTTPRequest($url = '', $method = 'POST', $params = [], $header) {
        if (!count($header)) {
            $header = ['Content-Type: application/json'];
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
