<?php

declare(strict_types=1);

namespace Flow\ETL\Cache;

use Flow\ETL\Cache;
use Flow\ETL\Rows;
use Flow\Serializer\NativePHPSerializer;
use Flow\Serializer\Serializer;
use Psr\SimpleCache\CacheInterface;

/**
 * @implements Cache<array{cache: CacheInterface, serializer: Serializer}>
 */
final class PSRSimpleCache implements Cache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Serializer $serializer = new NativePHPSerializer()
    ) {
    }

    public function __serialize() : array
    {
        return [
            'cache' => $this->cache,
            'serializer' => $this->serializer,
        ];
    }

    public function __unserialize(array $data) : void
    {
        $this->cache = $data['cache'];
        $this->serializer = $data['serializer'];
    }

    public function add(string $id, Rows $rows) : void
    {
        $rowsId = \uniqid($id, true);

        $this->addToIndex($id, $rowsId);
        $this->cache->set($rowsId, $this->serializer->serialize($rows));
    }

    public function clear(string $id) : void
    {
        foreach ($this->index($id) as $entry) {
            $this->cache->delete($entry);
        }
    }

    public function read(string $id) : \Generator
    {
        foreach ($this->index($id) as $entry) {
            if ($this->cache->has($entry)) {
                /**
                 * @var Rows $rows
                 *
                 * @phpstan-ignore-next-line
                 */
                $rows = $this->serializer->unserialize((string) $this->cache->get($entry));

                yield $rows;
            }
        }
    }

    private function addToIndex(string $indexId, string $id) : void
    {
        if (!$this->cache->has($indexId)) {
            $this->cache->set($indexId, [$id]);

            return;
        }

        /** @var array<string> $index */
        $index = $this->cache->get($indexId);
        $this->cache->set($indexId, \array_merge($index, [$id]));
    }

    /**
     * @param string $indexId
     *
     * @return array<string>
     */
    private function index(string $indexId) : array
    {
        if (!$this->cache->has($indexId)) {
            return [];
        }

        /** @var array<string> $index */
        $index = $this->cache->get($indexId);

        return $index;
    }
}
