<?php
/**
 * MIT License
 *
 * Copyright (c) 2025 Magentix
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
declare(strict_types=1);

namespace Magentix\MagentoApiClient;

use Magentix\MagentoApiClient\Interface\Cache;

class MagentoApiClient
{
    public function __construct(
        private string $consumerKey,
        private string $consumerSecret,
        private string $accessToken,
        private string $accessTokenSecret,
        private ?Cache $cache = null
    ) {
    }

    public function get(string $url, array $params = []): array
    {
        return $this->call('GET', $url, [], $params);
    }

    public function delete(string $url, array $params = []): array
    {
        return $this->call('DELETE', $url, [], $params);
    }

    public function post(string $url, array $body = []): array
    {
        return $this->call('POST', $url, $body);
    }

    public function put(string $url, array $body = []): array
    {
        return $this->call('PUT', $url, $body);
    }

    public function call(string $method, string $url, array $body = [], array $params = []): array
    {
        $method = strtoupper($method);

        if ($this->canCache($method)) {
            $cacheKey = sha1(serialize([$method, $url, $body, $params]));
            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
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

        $response = curl_exec($curl);

        curl_close($curl);

        if ($response === false) {
            return ['error' => true, 'result' => curl_error($curl)];
        }

        $data = json_decode($response, true);

        $result = [
            'error' => curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200,
            'result' => is_array($data) ? $data : $response,
        ];

        if (!$result['error'] && $this->canCache($method) && isset($cacheKey)) {
            $this->cache->set($cacheKey, $result);
        }

        return $result;
    }

    public function getCache(): ?Cache
    {
        return $this->cache;
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
        return md5(uniqid((string)rand(), true));
    }

    protected function urlEncode($value): string
    {
        return str_replace('%7E', '~', rawurlencode((string)$value));
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

    protected function canCache(string $method): bool
    {
        return $this->cache !== null && strtoupper($method) === 'GET';
    }
}
