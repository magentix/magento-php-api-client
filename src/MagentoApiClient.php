<?php

declare(strict_types=1);

namespace Magentix\MagentoApiClient;

use Exception;

class MagentoApiClient {

    public function __construct(
        private string $consumerKey,
        private string $consumerSecret,
        private string $accessToken,
        private string $accessTokenSecret
    ) {
    }

    public function request(string $method, string $url, array $body = [], array $params = []): array
    {
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->getUrl($url, $params),
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . $this->getOauth($method, $url, $params),
                'Content-Type: application/json',
            ]
        ];
        if (!empty($body)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $result = curl_exec($curl);

        curl_close($curl);

        return json_decode($result, true);
    }

    protected function getUrl(string $url, array $params): string
    {
        return $url . '?' . rawurldecode(http_build_query($params));
    }

    protected function getOauth(string $method, string $url, array $params): string
    {
        $oauth = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => $this->getNonce(),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];

        if (!empty($params)) {
            $query = rawurldecode(http_build_query($params));
            foreach (explode('&', $query) as $param) {
                $paramData = explode('=', $param);
                $oauth[$this->urlEncode($paramData[0])] = $this->urlEncode($paramData[1] ?? '');
            }
        }

        $base = [$method, $this->urlEncode($url), $this->urlencode($this->toByteValueOrderedQueryString($oauth))];

        $oauth['oauth_signature'] = base64_encode(
            hash_hmac('SHA256', implode('&', $base), $this->consumerSecret . '&' . $this->accessTokenSecret, true)
        );

        return http_build_query($oauth, '', ',');
    }

    protected function getNonce(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception) {
            return md5(uniqid((string)rand(), true));
        }
    }

    protected function urlEncode($value): string
    {
        $encoded = rawurlencode((string) $value);

        return str_replace('%7E', '~', $encoded);
    }

    protected function toByteValueOrderedQueryString(array $params): string
    {
        $return = [];
        uksort($params, 'strnatcmp');
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                natsort($value);
                foreach ($value as $duplicate) {
                    $return[] = $key . '=' . $duplicate;
                }
            } else {
                $return[] = $key . '=' . $value;
            }
        }
        return implode('&', $return);
    }
}
