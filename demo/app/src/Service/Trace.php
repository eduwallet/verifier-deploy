<?php

namespace Demo\Service;

/** The upstream exchanges made while handling one request, for the debug panels of the front-end. */
final class Trace
{
    /** @var list<array<string,mixed>> */
    private array $entries = [];

    /** @param array<string,mixed> $entry */
    public function add(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->entries;
    }
}
