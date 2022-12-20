<?php

namespace Supermetrolog\SynchronizerAlreadySyncRepo\interfaces;

use Supermetrolog\Synchronizer\interfaces\FileInterface;

interface RepositoryInterface
{
    public function findByName(string $filename): ?FileInterface;
    public function createOrUpdate(string $filename, string $content): bool;
    public function getContent(FileInterface $file): ?string;
}
