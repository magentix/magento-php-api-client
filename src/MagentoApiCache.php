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

use Exception;
use Magentix\MagentoApiClient\Interface\Cache;

class MagentoApiCache implements Cache
{
    private ?array $data = null;
    
    public function __construct(
        private int $lifetime = 3600,
        private string $cachePath = 'cache',
        private string $cacheName = 'default',
        private string $extension = '.cache'
    ) {
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $data): MagentoApiCache
    {
        $storeData = [
            'time' => time(),
            'expire' => $this->lifetime,
            'data' => serialize($data)
        ];

        $data = $this->load(true);
        $data[$key] = $storeData;

        $cacheData = json_encode($data);

        file_put_contents($this->getCacheFile(), $cacheData);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function get(string $key): mixed
    {
        $this->cleanByKey($key);

        if (!$this->isCached($key)) {
            return null;
        }

        $data = $this->load();

        return unserialize($data[$key]['data']);
    }

    /**
     * @throws Exception
     */
    public function all(): array
    {
        $results = [];
        $data = $this->load();

        if (empty($data)) {
            return $results;
        }

        foreach ($data as $key => $value) {
            $results[$key] = unserialize($value['data']);
        }

        return $results;
    }

    /**
     * @throws Exception
     */
    public function isCached(string $key): bool
    {
        $data = $this->load();

        if (empty($data)) {
            return false;
        }

        return isset($data[$key]);
    }

    /**
     * @throws Exception
     */
    public function cleanByKey(string $key): MagentoApiCache
    {
        $data = $this->load();

        if (!isset($data[$key])) {
            return $this;
        }

        if (!$this->isExpired($data[$key]['time'] ?? 0, $data[$key]['expire'] ?? 0)) {
            return $this;
        }

        unset($data[$key]);

        $this->data = $data;

        file_put_contents($this->getCacheFile(), json_encode($this->data));

        return $this;
    }

    /**
     * @throws Exception
     */
    public function cleanExpired(): MagentoApiCache
    {
        $data = $this->load();

        if (empty($data)) {
            return $this;
        }

        foreach ($data as $key => $entry) {
            if (!$this->isExpired($entry['time'] ?? 0, $entry['expire'] ?? 0)) {
                continue;
            }
            unset($data[$key]);
        }

        $this->data = $data;

        file_put_contents($this->getCacheFile(), json_encode($this->data));

        return $this;
    }

    /**
     * @throws Exception
     */
    public function cleanAll(): MagentoApiCache
    {
        $file = $this->getCacheFile();

        if (file_exists($file)) {
            $cacheFile = fopen($file, 'w');
            fclose($cacheFile);
        }

        $this->data = null;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getCacheFile(): string
    {
        if (!$this->checkCacheDir()) {
            return '';
        }

        $filename = preg_replace('/[^0-9a-z._\-]/i', '', strtolower($this->getCacheName()));

        return $this->getCachePath() . sha1($filename) . $this->getExtension();
    }

    public function setCachePath(string $path): MagentoApiCache
    {
        $this->cachePath = $path;

        return $this;
    }

    public function getCachePath(): string
    {
        return rtrim($this->cachePath, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function setCacheName(string $name): MagentoApiCache
    {
        $this->cacheName = $name;

        return $this;
    }

    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    public function setExtension(string $extension): MagentoApiCache
    {
        $this->extension = $extension;

        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setLifetime(int $lifetime): MagentoApiCache
    {
        $this->lifetime = $lifetime;

        return $this;
    }
    
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * @throws Exception
     */
    private function load($refresh = false): array
    {
        if (!$refresh && $this->data !== null) {
            return $this->data;
        }

        $file = $this->getCacheFile();
        if (!file_exists($file)) {
            return [];
        }

        $this->data = json_decode(file_get_contents($file), true) ?: [];

        return $this->data;
    }

    private function isExpired(int $timestamp, int $lifetime): bool
    {
        if ($lifetime === 0) {
            return true;
        }
        if (time() - $timestamp > $lifetime) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function checkCacheDir(): bool
    {
        if (!is_dir($this->getCachePath()) && !mkdir($this->getCachePath(), 0775, true)) {
            throw new Exception('Unable to create cache directory ' . $this->getCachePath());
        }

        if (!is_readable($this->getCachePath()) || !is_writable($this->getCachePath())) {
            if (!chmod($this->getCachePath(), 0775)) {
                throw new Exception($this->getCachePath() . ' must be readable and writeable');
            }
        }

        return true;
    }
}
