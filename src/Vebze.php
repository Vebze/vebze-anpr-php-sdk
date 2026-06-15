<?php

namespace Vebze;

use Exception;
use CURLFile;

class VebzeError extends Exception
{
    private $response;
    private $statusCode;

    public function __construct($message, $statusCode = null, $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getResponse()
    {
        return $this->response;
    }
}

class Vebze
{
    const DEFAULT_BASE_URL = "https://api.vebze.com/api/v1";

    private $apiKey;
    private $baseUrl;
    private $timeout;

    public function __construct($apiKey, $baseUrl = self::DEFAULT_BASE_URL, $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function snapshot($image)
    {
        $url = $this->baseUrl . "/snapshot";
        $headers = [
            "Authorization: Bearer " . $this->apiKey
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if (is_string($image) && strpos($image, "data:") === 0) {
            $headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["image" => $image]));
        } else {
            // Assume it's a file path
            if (!file_exists($image)) {
                throw new Exception("File not found: " . $image);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                "image" => new CURLFile($image)
            ]);
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return $this->handleResponse($response, $statusCode, $error);
    }

    public function balance()
    {
        return $this->get("/balance");
    }

    public function history($limit = 100)
    {
        return $this->get("/history", ["limit" => $limit]);
    }

    private function get($endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->apiKey,
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return $this->handleResponse($response, $statusCode, $error);
    }

    private function handleResponse($response, $statusCode, $error)
    {
        if ($error) {
            throw new VebzeError("cURL Error: " . $error, $statusCode);
        }

        $decoded = json_decode($response, true);
        
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = "HTTP " . $statusCode;
            if (isset($decoded['detail'])) {
                $message = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
            } elseif (isset($decoded['message'])) {
                $message = $decoded['message'];
            }
            throw new VebzeError($message, $statusCode, $decoded);
        }

        return $decoded;
    }
}
