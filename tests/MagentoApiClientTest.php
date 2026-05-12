<?php

declare(strict_types=1);

namespace Magentix\MagentoApiClient\Tests;

use Magentix\MagentoApiClient\MagentoApiCache;
use Magentix\MagentoApiClient\MagentoApiClient;
use PHPUnit\Framework\TestCase;

/**
 * Exposes protected methods for testing.
 */
class TestableMagentoApiClient extends MagentoApiClient
{
    public function normalizeUrl(string $url): string
    {
        return parent::normalizeUrl($url);
    }

    public function urlEncode($value): string
    {
        return parent::urlEncode($value);
    }

    public function toByteValueOrderedQueryString(array $params): string
    {
        return parent::toByteValueOrderedQueryString($params);
    }

    public function getUrl(string $url, array $params): string
    {
        return parent::getUrl($url, $params);
    }

    public function canCache(string $method): bool
    {
        return parent::canCache($method);
    }
}

class MagentoApiClientTest extends TestCase
{
    private TestableMagentoApiClient $client;

    private TestableMagentoApiClient $clientWithCache;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'magento-api-client-test';

        $this->client = new TestableMagentoApiClient('key', 'secret', 'token', 'token_secret');

        $this->clientWithCache = new TestableMagentoApiClient(
            'key',
            'secret',
            'token',
            'token_secret',
            new MagentoApiCache(3600, $this->cacheDir, 'test')
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }

    // =========================================================
    // getCache()
    // =========================================================

    public function testGetCacheReturnsNullWhenNoCacheProvided(): void
    {
        $this->assertNull($this->client->getCache());
    }

    public function testGetCacheReturnsCacheInstance(): void
    {
        $this->assertInstanceOf(MagentoApiCache::class, $this->clientWithCache->getCache());
    }

    // =========================================================
    // canCache()
    // =========================================================

    public function testCanCacheReturnsFalseWithoutCache(): void
    {
        $this->assertFalse($this->client->canCache('GET'));
    }

    public function testCanCacheReturnsTrueForGetWithCache(): void
    {
        $this->assertTrue($this->clientWithCache->canCache('GET'));
    }

    public function testCanCacheReturnsFalseForPostWithCache(): void
    {
        $this->assertFalse($this->clientWithCache->canCache('POST'));
    }

    public function testCanCacheReturnsFalseForPutWithCache(): void
    {
        $this->assertFalse($this->clientWithCache->canCache('PUT'));
    }

    public function testCanCacheReturnsFalseForDeleteWithCache(): void
    {
        $this->assertFalse($this->clientWithCache->canCache('DELETE'));
    }

    // =========================================================
    // normalizeUrl()
    // =========================================================

    public function testNormalizeUrlLeavesCleanUrlUnchanged(): void
    {
        $url = 'https://example.com/rest/V1/products';
        $this->assertSame($url, $this->client->normalizeUrl($url));
    }

    public function testNormalizeUrlDecodesPercentEncodedUnreservedChars(): void
    {
        // '-' is unreserved and should be decoded from %2D
        $this->assertSame(
            'https://example.com/rest/V1/products/SKU-001',
            $this->client->normalizeUrl('https://example.com/rest/V1/products/SKU%2D001')
        );
    }

    public function testNormalizeUrlKeepsPercentEncodedReservedChars(): void
    {
        // '/' is reserved and must stay encoded
        $this->assertSame(
            'https://example.com/rest/V1/products%2Fsku',
            $this->client->normalizeUrl('https://example.com/rest/V1/products%2Fsku')
        );
    }

    public function testNormalizeUrlUppercasesHexDigits(): void
    {
        $this->assertSame(
            'https://example.com/rest/V1/products%2Fsku',
            $this->client->normalizeUrl('https://example.com/rest/V1/products%2fsku')
        );
    }

    public function testNormalizeUrlDecodesAllUnreservedChars(): void
    {
        // A-Z a-z 0-9 - . _ ~  are all unreserved
        $this->assertSame(
            'https://example.com/path/a-b.c_d~e',
            $this->client->normalizeUrl('https://example.com/path/a%2Db%2Ec%5Fd%7Ee')
        );
    }

    public function testNormalizeUrlIncludesPort(): void
    {
        $this->assertSame(
            'https://example.com:8080/rest/V1/products',
            $this->client->normalizeUrl('https://example.com:8080/rest/V1/products')
        );
    }

    public function testNormalizeUrlStripsQueryString(): void
    {
        $this->assertSame(
            'https://example.com/rest/V1/products',
            $this->client->normalizeUrl('https://example.com/rest/V1/products?foo=bar')
        );
    }

    // =========================================================
    // urlEncode()
    // =========================================================

    public function testUrlEncodeLeavesTildeUnencoded(): void
    {
        $this->assertSame('hello~world', $this->client->urlEncode('hello~world'));
    }

    public function testUrlEncodeEncodesSpace(): void
    {
        $this->assertSame('hello%20world', $this->client->urlEncode('hello world'));
    }

    public function testUrlEncodeEncodesEquals(): void
    {
        $this->assertSame('hello%3Dworld', $this->client->urlEncode('hello=world'));
    }

    public function testUrlEncodeEncodesAmpersand(): void
    {
        $this->assertSame('hello%26world', $this->client->urlEncode('hello&world'));
    }

    public function testUrlEncodeEncodesSlash(): void
    {
        $this->assertSame('hello%2Fworld', $this->client->urlEncode('hello/world'));
    }

    public function testUrlEncodeLeavesAlphanumericUnencoded(): void
    {
        $this->assertSame('Hello123', $this->client->urlEncode('Hello123'));
    }

    public function testUrlEncodeCastsIntToString(): void
    {
        $this->assertSame('42', $this->client->urlEncode(42));
    }

    // =========================================================
    // toByteValueOrderedQueryString()
    // =========================================================

    public function testQueryStringIsSortedByKey(): void
    {
        $params = ['b' => '2', 'a' => '1', 'c' => '3'];
        $this->assertSame('a=1&b=2&c=3', $this->client->toByteValueOrderedQueryString($params));
    }

    public function testQueryStringWithArrayValueProducesMultipleEntries(): void
    {
        $params = ['a' => ['3', '1', '2']];
        $result = $this->client->toByteValueOrderedQueryString($params);
        $this->assertSame('a=1&a=2&a=3', $result);
    }

    public function testQueryStringWithOauthParamsSortsCorrectly(): void
    {
        $params = [
            'oauth_version' => '1.0',
            'oauth_token' => 'mytoken',
            'oauth_timestamp' => '1234567890',
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_nonce' => 'abc123',
            'oauth_consumer_key' => 'mykey',
        ];
        $result = $this->client->toByteValueOrderedQueryString($params);
        $this->assertStringStartsWith('oauth_consumer_key=', $result);
        $this->assertStringEndsWith('=1.0', $result);
    }

    public function testQueryStringWithEmptyParamsReturnsEmptyString(): void
    {
        $this->assertSame('', $this->client->toByteValueOrderedQueryString([]));
    }

    // =========================================================
    // getUrl()
    // =========================================================

    public function testGetUrlWithNoParams(): void
    {
        $this->assertSame(
            'https://example.com/rest/V1/products?',
            $this->client->getUrl('https://example.com/rest/V1/products', [])
        );
    }

    public function testGetUrlWithSimpleParams(): void
    {
        $result = $this->client->getUrl('https://example.com/api', ['page' => '1', 'limit' => '10']);
        $this->assertStringContainsString('page=1', $result);
        $this->assertStringContainsString('limit=10', $result);
    }

    public function testGetUrlDecodesEncodedParams(): void
    {
        // rawurldecode is applied on the query string
        $result = $this->client->getUrl('https://example.com/api', ['filter' => 'foo bar']);
        $this->assertStringContainsString('filter=foo+bar', $result);
    }

    public function testGetUrlWithNestedParams(): void
    {
        $result = $this->client->getUrl('https://example.com/api', [
            'searchCriteria' => ['pageSize' => 10],
        ]);
        $this->assertStringContainsString('searchCriteria', $result);
    }
}
