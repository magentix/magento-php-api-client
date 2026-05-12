<?php

declare(strict_types=1);

namespace Magentix\MagentoApiClient\Tests;

use Exception;
use Magentix\MagentoApiClient\MagentoApiCache;
use PHPUnit\Framework\TestCase;

class MagentoApiCacheTest extends TestCase
{
    private string $cacheDir;

    private MagentoApiCache $cache;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'magento-api-cache-test';
        $this->cache = new MagentoApiCache(3600, $this->cacheDir, 'test');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    // =========================================================
    // Helpers
    // =========================================================

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

    /**
     * Expire an already-stored entry by backdating its timestamp in the file.
     */
    private function expireEntry(string $key, int $lifetime = 3600): void
    {
        $cachePath = $this->cacheDir . DIRECTORY_SEPARATOR . 'test.cache';
        $data = json_decode(file_get_contents($cachePath), true);
        $data[$key][MagentoApiCache::KEY_TIME] = time() - $lifetime - 1;
        file_put_contents($cachePath, json_encode($data));
        $this->cache->setCacheFile('test');
    }

    // =========================================================
    // Construction
    // =========================================================

    /**
     * @throws Exception
     */
    public function testConstructorDefaultValues(): void
    {
        $cache = new MagentoApiCache();
        $this->assertSame(3600, $cache->getLifetime());
        $this->assertSame('.cache', $cache->getExtension());
        $this->assertSame('default', $cache->getCacheFile());
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, $cache->getCacheDir());

        $this->removeDir(rtrim($cache->getCacheDir(), DIRECTORY_SEPARATOR));
    }

    public function testConstructorCustomValues(): void
    {
        $this->assertSame(3600, $this->cache->getLifetime());
        $this->assertSame('test', $this->cache->getCacheFile());
        $this->assertStringContainsString('magento-api-cache-test', $this->cache->getCacheDir());
    }

    public function testCacheDirectoryIsCreatedOnFirstUse(): void
    {
        $this->cache->set('key', 'value');
        $this->assertDirectoryExists($this->cacheDir);
    }

    public function testGetCachePathReturnsFullPath(): void
    {
        $expected = $this->cacheDir . DIRECTORY_SEPARATOR . 'test.cache';
        $this->assertSame($expected, $this->cache->getCachePath());
    }

    // =========================================================
    // set() / get()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testSetAndGetString(): void
    {
        $this->cache->set('key', 'value');
        $this->assertSame('value', $this->cache->get('key'));
    }

    /**
     * @throws Exception
     */
    public function testSetAndGetArray(): void
    {
        $data = ['id' => 1, 'name' => 'John'];
        $this->cache->set('user', $data);
        $this->assertSame($data, $this->cache->get('user'));
    }

    /**
     * @throws Exception
     */
    public function testSetAndGetInteger(): void
    {
        $this->cache->set('count', 42);
        $this->assertSame(42, $this->cache->get('count'));
    }

    /**
     * @throws Exception
     */
    public function testSetAndGetNullValue(): void
    {
        $this->cache->set('nullkey', null);
        $this->assertTrue($this->cache->isCached('nullkey'));
        $this->assertNull($this->cache->get('nullkey'));
    }

    /**
     * @throws Exception
     */
    public function testGetNonExistentKeyReturnsNull(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
    }

    /**
     * @throws Exception
     */
    public function testGetNonExistentKeyDoesNotModifyFile(): void
    {
        $this->cache->set('other', 'data');
        $contentBefore = file_get_contents($this->cache->getCachePath());

        $this->cache->get('nonexistent');

        $this->assertSame($contentBefore, file_get_contents($this->cache->getCachePath()));
    }

    /**
     * @throws Exception
     */
    public function testSetReturnsSelf(): void
    {
        $this->assertSame($this->cache, $this->cache->set('key', 'value'));
    }

    // =========================================================
    // Expiry
    // =========================================================

    /**
     * @throws Exception
     */
    public function testGetExpiredKeyReturnsNull(): void
    {
        $this->cache->set('expired', 'value');
        $this->expireEntry('expired');

        $this->assertNull($this->cache->get('expired'));
    }

    /**
     * @throws Exception
     */
    public function testGetExpiredKeyRemovesEntryFromFile(): void
    {
        $this->cache->set('expired', 'value');
        $this->expireEntry('expired');
        $this->cache->get('expired');

        $this->cache->setCacheFile('test');
        $this->assertFalse($this->cache->isCached('expired'));
    }

    /**
     * @throws Exception
     */
    public function testValidKeyIsNotRemovedOnGet(): void
    {
        $this->cache->set('valid', 'value');
        $this->cache->get('valid');

        $this->assertTrue($this->cache->isCached('valid'));
    }

    // =========================================================
    // canCache() / lifetime
    // =========================================================

    public function testCanCacheWithPositiveLifetime(): void
    {
        $this->assertTrue($this->cache->canCache());
    }

    public function testCanCacheWithZeroLifetime(): void
    {
        $this->cache->setLifetime(0);
        $this->assertFalse($this->cache->canCache());
    }

    /**
     * @throws Exception
     */
    public function testSetDoesNothingWhenLifetimeIsZero(): void
    {
        $this->cache->setLifetime(0);
        $this->cache->set('key', 'value');

        $this->assertFalse($this->cache->isCached('key'));
    }

    /**
     * @throws Exception
     */
    public function testBulkDoesNothingWhenLifetimeIsZero(): void
    {
        $this->cache->setLifetime(0);
        $this->cache->bulk(['k1' => 'v1', 'k2' => 'v2']);

        $this->assertFalse($this->cache->isCached('k1'));
        $this->assertFalse($this->cache->isCached('k2'));
    }

    /**
     * No exception when calling clean operations with lifetime=0 and no directory.
     *
     * @throws Exception
     */
    public function testCleanOperationsWithZeroLifetimeAndNoDirectory(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'magento-api-cache-no-dir';
        $cache = new MagentoApiCache(0, $dir, 'test');

        $cache->get('key');
        $cache->cleanAll();
        $cache->cleanExpired();
        $cache->cleanByKey('key');

        $this->assertDirectoryDoesNotExist($dir);
    }

    // =========================================================
    // Per-entry lifetime
    // =========================================================

    /**
     * @throws Exception
     */
    public function testEachEntryUsesItsOwnStoredLifetime(): void
    {
        $this->cache->setLifetime(60);
        $this->cache->set('short', 'val1');

        $this->cache->setLifetime(3600);
        $this->cache->set('long', 'val2');

        $this->expireEntry('short', 60);

        $this->assertNull($this->cache->get('short'));
        $this->assertSame('val2', $this->cache->get('long'));
    }

    /**
     * Expired entry must be purged from file even when current lifetime=0.
     * Verifies the persist() is not blocked by canCache() in this scenario.
     *
     * @throws Exception
     */
    public function testExpiredEntryIsPurgedFromFileWhenCurrentLifetimeIsZero(): void
    {
        $this->cache->set('key', 'value');
        $this->expireEntry('key', 3600);

        $cacheWithZeroLifetime = new MagentoApiCache(0, $this->cacheDir, 'test');
        $cacheWithZeroLifetime->get('key');

        $reloaded = new MagentoApiCache(3600, $this->cacheDir, 'test');
        $this->assertFalse($reloaded->isCached('key'));
    }

    // =========================================================
    // isCached()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testIsCachedReturnsTrueForStoredKey(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->isCached('key'));
    }

    public function testIsCachedReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->isCached('nonexistent'));
    }

    // =========================================================
    // bulk()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testBulkStoresMultipleKeys(): void
    {
        $this->cache->bulk(['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3']);

        $this->assertSame('v1', $this->cache->get('k1'));
        $this->assertSame('v2', $this->cache->get('k2'));
        $this->assertSame('v3', $this->cache->get('k3'));
    }

    /**
     * @throws Exception
     */
    public function testBulkReturnsSelf(): void
    {
        $this->assertSame($this->cache, $this->cache->bulk(['k' => 'v']));
    }

    // =========================================================
    // all()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testAllReturnsAllCachedData(): void
    {
        $this->cache->bulk(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->cache->all());
    }

    public function testAllReturnsEmptyArrayWhenNothingCached(): void
    {
        $this->assertSame([], $this->cache->all());
    }

    // =========================================================
    // cleanByKey()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testCleanByKeyRemovesExpiredKey(): void
    {
        $this->cache->set('expired', 'value');
        $this->cache->set('valid', 'data');
        $this->expireEntry('expired');

        $this->cache->cleanByKey('expired');

        $this->assertFalse($this->cache->isCached('expired'));
        $this->assertTrue($this->cache->isCached('valid'));
    }

    /**
     * @throws Exception
     */
    public function testCleanByKeyOnNonExistentKeyDoesNotModifyFile(): void
    {
        $this->cache->set('existing', 'data');
        $contentBefore = file_get_contents($this->cache->getCachePath());

        $this->cache->cleanByKey('nonexistent');

        $this->assertSame($contentBefore, file_get_contents($this->cache->getCachePath()));
    }

    /**
     * @throws Exception
     */
    public function testCleanByKeyOnValidKeyDoesNothing(): void
    {
        $this->cache->set('valid', 'value');
        $this->cache->cleanByKey('valid');

        $this->assertTrue($this->cache->isCached('valid'));
    }

    /**
     * @throws Exception
     */
    public function testCleanByKeyReturnsSelf(): void
    {
        $this->assertSame($this->cache, $this->cache->cleanByKey('key'));
    }

    // =========================================================
    // cleanExpired()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testCleanExpiredRemovesOnlyExpiredKeys(): void
    {
        $this->cache->set('valid', 'value');
        $this->cache->set('exp1', 'old1');
        $this->cache->set('exp2', 'old2');
        $this->expireEntry('exp1');
        $this->expireEntry('exp2');

        $this->cache->cleanExpired();

        $this->assertTrue($this->cache->isCached('valid'));
        $this->assertFalse($this->cache->isCached('exp1'));
        $this->assertFalse($this->cache->isCached('exp2'));
    }

    /**
     * @throws Exception
     */
    public function testCleanExpiredPersistsToFile(): void
    {
        $this->cache->set('expired', 'old');
        $this->expireEntry('expired');
        $this->cache->cleanExpired();

        $this->cache->setCacheFile('test');
        $this->assertFalse($this->cache->isCached('expired'));
    }

    // =========================================================
    // cleanAll()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testCleanAllRemovesAllKeys(): void
    {
        $this->cache->bulk(['k1' => 'v1', 'k2' => 'v2']);
        $this->cache->cleanAll();

        $this->assertSame([], $this->cache->all());
    }

    /**
     * @throws Exception
     */
    public function testCleanAllPersistsToFile(): void
    {
        $this->cache->bulk(['k1' => 'v1', 'k2' => 'v2']);
        $this->cache->cleanAll();

        $this->cache->setCacheFile('test');
        $this->assertSame([], $this->cache->all());
    }

    // =========================================================
    // Persistence between instances
    // =========================================================

    /**
     * @throws Exception
     */
    public function testDataPersistsBetweenInstances(): void
    {
        $this->cache->set('persistent', 'hello');

        $cache2 = new MagentoApiCache(3600, $this->cacheDir, 'test');
        $this->assertSame('hello', $cache2->get('persistent'));
    }

    /**
     * @throws Exception
     */
    public function testCleanAllPersistsBetweenInstances(): void
    {
        $this->cache->set('key', 'value');
        $this->cache->cleanAll();

        $cache2 = new MagentoApiCache(3600, $this->cacheDir, 'test');
        $this->assertFalse($cache2->isCached('key'));
    }

    // =========================================================
    // setCacheDir() / setCacheFile() / setExtension()
    // =========================================================

    /**
     * @throws Exception
     */
    public function testSetCacheDirReloadsFromNewDirectory(): void
    {
        $this->cache->set('key', 'value');

        $newDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'magento-api-cache-test-alt';
        $this->cache->setCacheDir($newDir);

        $this->assertFalse($this->cache->isCached('key'));

        $this->removeDir($newDir);
    }

    /**
     * @throws Exception
     */
    public function testSetCacheFileReloadsFromNewFile(): void
    {
        $this->cache->set('key', 'value');
        $this->cache->setCacheFile('other');

        $this->assertFalse($this->cache->isCached('key'));
    }

    /**
     * @throws Exception
     */
    public function testSetExtensionReloadsFromNewFile(): void
    {
        $this->cache->set('key', 'value');
        $this->cache->setExtension('.json');

        $this->assertFalse($this->cache->isCached('key'));
    }

    /**
     * @throws Exception
     */
    public function testSetCacheFileIsolatesDataBetweenFiles(): void
    {
        $this->cache->set('product', 'data1');

        $this->cache->setCacheFile('category');
        $this->cache->set('category', 'data2');

        $this->assertFalse($this->cache->isCached('product'));
        $this->assertTrue($this->cache->isCached('category'));

        $this->cache->setCacheFile('test');
        $this->assertTrue($this->cache->isCached('product'));
        $this->assertFalse($this->cache->isCached('category'));
    }

    // =========================================================
    // Filename sanitization
    // =========================================================

    /**
     * @throws Exception
     */
    public function testFilenameSanitizationReplacesInvalidChars(): void
    {
        $cache = new MagentoApiCache(3600, $this->cacheDir, 'my_file-name');
        $this->assertSame('my-file-name', $cache->getCacheFile());
    }

    /**
     * @throws Exception
     */
    public function testFilenameSanitizationPreventsPathTraversal(): void
    {
        $cache = new MagentoApiCache(3600, $this->cacheDir, '../../../etc/passwd');
        $this->assertSame('..-..-..-etc-passwd', $cache->getCacheFile());
    }

    /**
     * @throws Exception
     */
    public function testFilenameSanitizationAllowsDotsAndAlphanumeric(): void
    {
        $cache = new MagentoApiCache(3600, $this->cacheDir, 'my.cache.v2');
        $this->assertSame('my.cache.v2', $cache->getCacheFile());
    }

    // =========================================================
    // Path separator normalization
    // =========================================================

    /**
     * @throws Exception
     */
    public function testPathSeparatorsAreNormalized(): void
    {
        $rawPath = $this->cacheDir . '/sub\\dir';
        $cache = new MagentoApiCache(3600, $rawPath, 'test');

        $expected = $this->cacheDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR;
        $this->assertSame($expected, $cache->getCacheDir());
    }

    /**
     * @throws Exception
     */
    public function testCacheDirAlwaysEndsWithSeparator(): void
    {
        $cache = new MagentoApiCache(3600, $this->cacheDir, 'test');
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, $cache->getCacheDir());
    }
}
