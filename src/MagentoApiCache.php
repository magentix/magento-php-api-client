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
    private array $data = [];

    /**
     * @throws Exception
     */
    public function __construct(
        private int $lifetime = 3600,
        private string $cachePath = 'cache',
        private string $cacheName = 'default',
        private string $extension = '.cache'
    ) {
        $this->load();
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $data): MagentoApiCache
    {
        $this->data[$key] = $this->value($data);

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function bulk(array $data): MagentoApiCache
    {
        foreach ($data as $key => $item) {
            $this->data[$key] = $this->value($item);
        }

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function get(string $key): mixed
    {
        if (!$this->cleanByKey($key)->isCached($key)) {
            return null;
        }

        return unserialize($this->data[$key]['data']);
    }

    public function all(): array
    {
        return array_map(function ($value) { return unserialize($value['data']); }, $this->data);
    }

    public function isCached(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * @throws Exception
     */
    public function cleanByKey(string $key): MagentoApiCache
    {
        if (!$this->isCached($key)) {
            return $this;
        }
        if (!$this->isExpired($key)) {
            return $this;
        }

        unset($this->data[$key]);

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function cleanExpired(): MagentoApiCache
    {
        foreach ($this->data as $key => $item) {
            if (!$this->isExpired($key)) {
                continue;
            }
            unset($this->data[$key]);
        }

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function cleanAll(): MagentoApiCache
    {
        $this->data = [];

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getCacheFile(): string
    {
        $this->checkCacheDir();

        $filename = preg_replace('/[^0-9a-z._\-]/i', '', strtolower($this->getCacheName()));

        return $this->getCachePath() . $filename . $this->getExtension();
    }

    /**
     * @throws Exception
     */
    public function setCachePath(string $path): MagentoApiCache
    {
        $this->cachePath = $path;
        $this->load();

        return $this;
    }

    public function getCachePath(): string
    {
        return rtrim($this->cachePath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * @throws Exception
     */
    public function setCacheName(string $name): MagentoApiCache
    {
        $this->cacheName = $name;
        $this->load();

        return $this;
    }

    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    /**
     * @throws Exception
     */
    public function setExtension(string $extension): MagentoApiCache
    {
        $this->extension = $extension;
        $this->load();

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

    private function value(mixed $data): array
    {
        return ['time' => time(), 'lifetime' => $this->lifetime, 'data' => serialize($data)];
    }

    /**
     * @throws Exception
     */
    private function persist(): void
    {
        file_put_contents($this->getCacheFile(), json_encode($this->data, JSON_PRETTY_PRINT));
    }

    /**
     * @throws Exception
     */
    private function load(): void
    {
        $this->data = [];

        $file = $this->getCacheFile();
        if (file_exists($file)) {
            $this->data = json_decode(file_get_contents($file), true) ?: [];
        }
    }

    private function isExpired(string $key): bool
    {
        if (!$this->isCached($key)) {
            return true;
        }
        if ($this->data[$key]['lifetime'] === 0) {
            return true;
        }
        if (time() - $this->data[$key]['time'] > $this->data[$key]['lifetime']) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function checkCacheDir(): void
    {
        if (!is_dir($this->getCachePath()) && !mkdir($this->getCachePath(), 0775, true)) {
            throw new Exception('Unable to create cache directory ' . $this->getCachePath());
        }

        if (!is_readable($this->getCachePath()) || !is_writable($this->getCachePath())) {
            if (!chmod($this->getCachePath(), 0775)) {
                throw new Exception($this->getCachePath() . ' must be readable and writable');
            }
        }
    }
}
