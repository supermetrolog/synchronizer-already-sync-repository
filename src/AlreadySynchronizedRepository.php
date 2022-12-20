<?php

namespace Supermetrolog\SynchronizerAlreadySyncRepo;

use LogicException;
use Supermetrolog\Synchronizer\interfaces\AlreadySynchronizedRepositoryInterface;
use Supermetrolog\Synchronizer\interfaces\FileInterface;
use Supermetrolog\SynchronizerAlreadySyncRepo\interfaces\RepositoryInterface;

class AlreadySynchronizedRepository implements AlreadySynchronizedRepositoryInterface
{
    private RepositoryInterface $repository;
    private string $filename;
    /** @var FileInterface[] $files */
    private array $files = [];
    /** @var FileInterface[] $dirtyFiles */
    private array $dirtyFiles = [];

    public function __construct(RepositoryInterface $repository, string $filename)
    {
        $this->filename = $filename;
        $this->repository = $repository;
        $this->loadFiles();
    }
    private function loadFiles(): void
    {
        if (($metadataFile = $this->repository->findByName($this->filename)) === null) {
            return;
        }
        if (($content = $this->repository->getContent($metadataFile)) === null) {
            return;
        }
        if (!($files = unserialize($content))) {
            throw new LogicException("invalid content");
        }
        $this->files = $files;
    }
    public function isEmpty(): bool
    {
        return count($this->files) === 0;
    }

    public function findFile(FileInterface $file): ?FileInterface
    {
        if (array_key_exists($file->getUniqueName(), $this->files)) {
            return $this->files[$file->getUniqueName()];
        }
        return null;
    }

    public function markFileAsDirty(FileInterface $file): void
    {
        $this->dirtyFiles[$file->getUniqueName()] = $file;
    }

    /** @return FileInterface[] */
    public function getNotDirtyFiles(): array
    {
        $files = [];
        foreach ($this->files as $uniqueName => $file) {
            if (!array_key_exists($uniqueName, $this->dirtyFiles)) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /**
     * @param FileInterface[] $createdFiles
     * @param FileInterface[] $updatedFiles
     * @param FileInterface[] $removedFiles
     */
    public function updateRepository(
        array $createdFiles,
        array $updatedFiles,
        array $removedFiles
    ): void {
        $this->removeFiles($removedFiles);
        $this->createFiles($createdFiles);
        $this->updateFiles($updatedFiles);
        $this->createOrUpdateSyncFile();
    }
    /**
     * @param FileInterface[] $files
     */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (!$this->hasFile($file)) {
                throw new LogicException("file not found");
            }
            unset($this->files[$file->getUniqueName()]);
        }
    }
    /**
     * @param FileInterface[] $files
     */
    private function createFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->files[$file->getUniqueName()] = $file;
        }
    }
    /**
     * @param FileInterface[] $files
     */
    private function updateFiles(array $files): void
    {
        foreach ($files as $file) {
            if (!$this->hasFile($file)) {
                throw new LogicException("file not found");
            }
            $this->files[$file->getUniqueName()] = $file;
        }
    }
    private function hasFile(FileInterface $file): bool
    {
        if (array_key_exists($file->getUniqueName(), $this->files)) {
            return true;
        }
        return false;
    }
    private function createOrUpdateSyncFile(): void
    {
        if (!$this->repository->createOrUpdate($this->filename, serialize($this->files))) {
            throw new LogicException("Error creating or updating sync file");
        }
    }
}
