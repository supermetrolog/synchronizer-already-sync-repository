<?php

namespace Supermetrolog\SynchronizerAlreadySyncRepo\interfaces;

interface RepositoryInterface
{
    public function createOrUpdate(string $filename, string $content): bool;
    public function getContentByFilename(string $filename): ?string;
}
