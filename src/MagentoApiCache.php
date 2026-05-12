<?php
/**
 * MIT License
 *
 * Copyright (c) 2026 Magentix
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
    public const KEY_DATA = 'data';

    public const KEY_LIFETIME = 'lifetime';

    public const KEY_TIME = 'time';

    private array $data = [];

    private string $cachePath;

    private string $cacheName;

    /**
     * @throws Exception
     */
    public function __construct(
        private int $lifetime = 3600,
        string $cachePath = 'cache',
        string $cacheName = 'default',
        private string $extension = '.cache'
    ) {
        $this->setCacheDir($cachePath);
        $this->setCacheFile($cacheName);
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $data): MagentoApiCache
    {
        if (!$this->canCache()) {
            return $this;
        }

        $this->data[$key] = $this->value($data);

        $this->persist();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function bulk(array $data): MagentoApiCache
    {
        if (!$this->canCache()) {
            return $this;
        }

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

        return unserialize($this->data[$key][self::KEY_DATA]);
    }

    public function all(): array
    {
        return array_map(function ($value) { return unserialize($value[self::KEY_DATA]); }, $this->data);
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
        if (!$this->isCached($key) || !$this->isExpired($key)) {
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
    public function getCachePath(): string
    {
        $this->checkCacheDir();

        return $this->getCacheDir() . $this->getCacheFile() . $this->getExtension();
    }

    /**
     * @throws Exception
     */
    public function setCacheDir(string $path): MagentoApiCache
    {
        $path = preg_replace('/[\/\\\\]/', DIRECTORY_SEPARATOR, $path);
        $this->cachePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (isset($this->cacheName)) {
            $this->load();
        }

        return $this;
    }

    public function getCacheDir(): string
    {
        return $this->cachePath;
    }

    /**
     * @throws Exception
     */
    public function setCacheFile(string $name): MagentoApiCache
    {
        $this->cacheName = preg_replace('/[^0-9a-zA-Z.]/', '-', $name);
        $this->load();

        return $this;
    }

    public function getCacheFile(): string
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

    public function canCache(): bool
    {
        return $this->getLifetime() > 0;
    }

    /**
     * @throws Exception
     */
    protected function persist(): void
    {
        if (!is_dir($this->getCacheDir())) {
            return;
        }

        if (file_put_contents($this->getCachePath(), json_encode($this->data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Unable to write cache file ' . $this->getCachePath());
        }
    }

    /**
     * @throws Exception
     */
    protected function load(): void
    {
        $this->data = [];

        $file = $this->getCachePath();
        if (file_exists($file)) {
            $this->data = json_decode(file_get_contents($file), true) ?: [];
        }
    }

    protected function isExpired(string $key): bool
    {
        if (!$this->isCached($key)) {
            return true;
        }
        if ($this->data[$key][self::KEY_LIFETIME] <= 0) {
            return true;
        }
        if (time() - $this->data[$key][self::KEY_TIME] > $this->data[$key][self::KEY_LIFETIME]) {
            return true;
        }

        return false;
    }

    protected function value(mixed $data): array
    {
        return [
            self::KEY_TIME => time(),
            self::KEY_LIFETIME => $this->getLifetime(),
            self::KEY_DATA => serialize($data),
        ];
    }

    /**
     * @throws Exception
     */
    private function checkCacheDir(): void
    {
        if (!$this->canCache()) {
            return;
        }

        if (!is_dir($this->getCacheDir()) && !mkdir($this->getCacheDir(), 0775, true)) {
            throw new Exception('Unable to create cache directory ' . $this->getCacheDir());
        }

        if (!is_readable($this->getCacheDir()) || !is_writable($this->getCacheDir())) {
            if (!chmod($this->getCacheDir(), 0775)) {
                throw new Exception($this->getCacheDir() . ' must be readable and writable');
            }
        }
    }
}
