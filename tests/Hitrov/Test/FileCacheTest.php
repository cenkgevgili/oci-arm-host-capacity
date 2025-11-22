<?php

namespace Hitrov\Test;

use Hitrov\FileCache;
use Hitrov\Test\Traits\DefaultConfig;
use Hitrov\Test\Traits\LoadEnv;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    const CONFIG_MD5 = '0c4b5682ece1704df5bf11d71fa55177';
    const ENV_FILENAME = '.env.test';

    use DefaultConfig, LoadEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadEnv();

        if (file_exists($this->getCacheFilename())) {
            unlink($this->getCacheFilename());
        }
    }

    public function testGetCacheKey(): void
    {
        $config = $this->getDefaultConfig();
        $cache = new FileCache($config);

        $this->assertEquals(
            self::CONFIG_MD5,
            $cache->getCacheKey('foo'),
        );
    }

    public function testCacheFileCreated(): void
    {
        $config = $this->getDefaultConfig();
        $api = $this->getDefaultApi();

        $api->setCache(new FileCache($config));

        $this->assertTrue(
            file_exists(sprintf('%s/%s', getcwd(), 'oci_cache.json')),
        );
    }

    public function testAddsCacheFileContents()
    {
        $config = $this->getDefaultConfig();
        $cache = new FileCache($config);

        $cache->add([1, 'one'], 'foo');

        $expected = [
            "foo" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    1,
                    "one"
                ]
            ]
        ];

        $this->assertEquals(
            $expected,
            json_decode(file_get_contents($this->getCacheFilename()), true),
        );
    }

    public function testUpdatesCacheFileContents()
    {
        $config = $this->getDefaultConfig();
        $cache = new FileCache($config);

        $existingCache = json_encode([
            "foo" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    1,
                    "one"
                ]
            ]
        ], JSON_PRETTY_PRINT);

        file_put_contents($this->getCacheFilename(), $existingCache);

        $cache->add([2, 'two'], 'bar');

        $expected = [
            "foo" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    1,
                    "one"
                ]
            ],
            "bar" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    2,
                    "two"
                ]
            ]
        ];

        $this->assertEquals(
            $expected,
            json_decode(file_get_contents($this->getCacheFilename()), true),
        );
    }

    public function testUpdatesWithDifferentConfig()
    {
        $config = $this->getDefaultConfig();
        $config->bootVolumeId = 'baz';
        $cache = new FileCache($config);

        $existingCache = json_encode([
            "foo" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    1,
                    "one"
                ]
            ]
        ], JSON_PRETTY_PRINT);

        file_put_contents($this->getCacheFilename(), $existingCache);

        $cache->add([11, 'eleven'], 'foo');

        $expected = [
            "foo" => [
                "0c4b5682ece1704df5bf11d71fa55177" => [
                    1,
                    "one"
                ],
                "b11f9e5fbe425f149a45af5a9fb40d66" => [
                    11,
                    "eleven"
                ]
            ]
        ];

        $this->assertEquals(
            $expected,
            json_decode(file_get_contents($this->getCacheFilename()), true),
        );
    }

    public function testGet()
    {
        $config = $this->getDefaultConfig();
        $cache = new FileCache($config);

        $cache->add([1, 'one'], 'foo');

        $this->assertEquals(
            [1, 'one'],
            $cache->get('foo'),
        );
    }

    private function getCacheFilename(): string
    {
        return sprintf('%s/%s', getcwd(), 'oci_cache.json');
    }
}