<?php

namespace tests\unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Supermetrolog\Synchronizer\interfaces\FileInterface;
use Supermetrolog\SynchronizerAlreadySyncRepo\AlreadySynchronizedRepository;
use Supermetrolog\SynchronizerAlreadySyncRepo\interfaces\RepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AlreadySynchronizedRepositoryTest extends TestCase
{
    private RepositoryInterface $repository;
    private string $filename;
    private FileInterface $metadataFile;
    private FileInterface $file1;
    private FileInterface $file2;
    private FileInterface $dir;

    public function setUp(): void
    {
        $this->repository = $this->createMock(RepositoryInterface::class);
        $this->metadataFile = $this->createMock(FileInterface::class);
        $this->filename = "sync-file.data";
        $this->generateFiles();
    }
    private function generateFiles(): void
    {
        /** @var MockObject $file1 */
        $file1 = $this->createMock(FileInterface::class);
        $file1->method('getUniqueName')->willReturn("/file1.txt");
        $file1->method('isDir')->willReturn(false);
        $file1->method('getHash')->willReturn("file1");
        /** @var MockObject $file2 */
        $file2 = $this->createMock(FileInterface::class);
        $file2->method('getUniqueName')->willReturn("/test/file2.txt");
        $file2->method('isDir')->willReturn(false);
        $file2->method('getHash')->willReturn("file2");

        /** @var MockObject $dir */
        $dir = $this->createMock(FileInterface::class);
        $dir->method('getUniqueName')->willReturn("/test/dir");
        $dir->method('isDir')->willReturn(true);
        $dir->method('getHash')->willReturn("");

        /** @var FileInterface $file1 */
        /** @var FileInterface $file2 */
        /** @var FileInterface $dir */

        $this->file1 = $file1;
        $this->file2 = $file2;
        $this->dir = $dir;
    }

    public function testConstructorWithInvalidContent(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;

        $repo->method('findByName')->willReturn($this->metadataFile);
        $repo->method('getContent')->willReturn("invalid content");
        $this->expectException(LogicException::class);
        new AlreadySynchronizedRepository($this->repository, $this->filename);
    }



    public function testIsEmptyTrue(): void
    {
        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $this->assertTrue($alreadyRepo->isEmpty());
    }

    public function testIsEmptyFalse(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;

        $repo->method('findByName')->willReturn($this->metadataFile);
        $repo->method('getContent')->willReturn(serialize([
            $this->createMock(FileInterface::class),
            $this->createMock(FileInterface::class),
            $this->createMock(FileInterface::class),
            $this->createMock(FileInterface::class),
        ]));

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $this->assertFalse($alreadyRepo->isEmpty());
    }

    public function testFindNotExistFile(): void
    {
        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $this->assertNull($alreadyRepo->findFile($this->createMock(FileInterface::class)));
    }

    public function testFindExistFile(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;

        $repo->method('findByName')->willReturn($this->metadataFile);

        $repo->method('getContent')->willReturn(serialize([
            $this->file1->getUniqueName() => $this->file1,
            $this->file2->getUniqueName() => $this->file2,
            $this->dir->getUniqueName() => $this->dir,
        ]));

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);

        $findedFile1 = $alreadyRepo->findFile($this->file1);
        $findedFile2 = $alreadyRepo->findFile($this->file2);
        $findedDir = $alreadyRepo->findFile($this->dir);

        $this->assertEquals(
            $this->file1->getUniqueName(),
            $findedFile1->getUniqueName()
        );
        $this->assertEquals($this->file1->isDir(), $findedFile1->isDir());
        $this->assertEquals($this->file1->getHash(), $findedFile1->getHash());
        $this->assertEquals(
            $this->file2->getUniqueName(),
            $findedFile2->getUniqueName()
        );
        $this->assertEquals($this->file2->isDir(), $findedFile2->isDir());
        $this->assertEquals($this->file2->getHash(), $findedFile2->getHash());
        $this->assertEquals(
            $this->dir->getUniqueName(),
            $findedDir->getUniqueName()
        );
        $this->assertEquals($this->dir->isDir(), $findedDir->isDir());
        $this->assertEquals($this->dir->getHash(), $findedDir->getHash());
    }

    public function testGetNotDirtyFilesWithEmptyRepo(): void
    {
        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $alreadyRepo->markFileAsDirty($this->file1);
        $alreadyRepo->markFileAsDirty($this->file2);
        $alreadyRepo->markFileAsDirty($this->dir);

        $this->assertEmpty($alreadyRepo->getNotDirtyFiles());
    }

    public function testGetNotDirtyFilesWithNotEmptyRepo(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;

        $repo->method('findByName')->willReturn($this->metadataFile);

        $repo->method('getContent')->willReturn(serialize([
            $this->file1->getUniqueName() => $this->file1,
            $this->file2->getUniqueName() => $this->file2,
            $this->dir->getUniqueName() => $this->dir,
        ]));

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $alreadyRepo->markFileAsDirty($this->file1);
        $alreadyRepo->markFileAsDirty($this->dir);

        $notDirtyFiles = $alreadyRepo->getNotDirtyFiles();
        $this->assertNotEmpty($alreadyRepo->getNotDirtyFiles());
        $this->assertCount(1, $notDirtyFiles);
        $this->assertEquals($this->file2->getUniqueName(), $notDirtyFiles[0]->getUniqueName());
        $this->assertEquals($this->file2->isDir(), $notDirtyFiles[0]->isDir());
        $this->assertEquals($this->file2->getHash(), $notDirtyFiles[0]->getHash());
    }
    public function testUpdateRepositoryWithEmptyRepoAndCreateOrUpdateError(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->expects($this->once())->method('createOrUpdate')->willReturn(false);
        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);

        $this->expectException(LogicException::class);
        $alreadyRepo->updateRepository([$this->file1], [], []);
    }

    public function testUpdateRepositoryWithEmptyRepoAndCreatedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $actualFilename = "";
        $repo->expects($this->once())->method('createOrUpdate')->will(
            $this->returnCallback(
                function ($filename) use (&$actualFilename) {
                    $actualFilename = $filename;
                    return true;
                }
            )
        );
        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $createdFiles = [
            $this->file1,
            $this->file2,
            $this->dir,
        ];
        $alreadyRepo->updateRepository($createdFiles, [], []);

        $this->assertEquals($this->filename, $actualFilename);
        $this->assertNotNull($alreadyRepo->findFile($this->file1));
        $this->assertNotNull($alreadyRepo->findFile($this->file2));
        $this->assertNotNull($alreadyRepo->findFile($this->dir));
    }

    public function testUpdateRepositoryWithNotEmptyRepoAndCreatedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->method('findByName')->willReturn($this->metadataFile);

        $repo->method('getContent')->willReturn(serialize([
            $this->file1->getUniqueName() => $this->file1,
            $this->dir->getUniqueName() => $this->dir,
        ]));

        $repo->expects($this->once())->method('createOrUpdate')->willReturn(true);

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $createdFiles = [
            $this->file1,
            $this->file2,
            $this->dir,
        ];
        $alreadyRepo->updateRepository($createdFiles, [], []);

        $this->assertNotNull($alreadyRepo->findFile($this->file1));
        $this->assertNotNull($alreadyRepo->findFile($this->file2));
        $this->assertNotNull($alreadyRepo->findFile($this->dir));
    }

    public function testUpdateRepositoryWithEmptyRepoAndUpdatedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->method('createOrUpdate')->willReturn(true);

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);

        $updatedFiles = [
            $this->file1,
            $this->file2,
            $this->dir,
        ];

        $this->expectException(LogicException::class);
        $alreadyRepo->updateRepository([], $updatedFiles, []);
    }

    public function testUpdateRepositoryWithNotEmptyRepoAndUpdatedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->method('findByName')->willReturn($this->metadataFile);

        $repo->method('getContent')->willReturn(serialize([
            $this->file1->getUniqueName() => $this->file1,
            $this->file2->getUniqueName() => $this->file2,
            $this->dir->getUniqueName() => $this->dir,
        ]));

        $repo->expects($this->once())->method('createOrUpdate')->willReturn(true);

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);

        /** @var MockObject $updatedFile1 */
        $updatedFile1 = $this->createMock(FileInterface::class);

        $updatedFile1->method("getHash")->willReturn("updatedFile1");
        $updatedFile1->method("isDir")->willReturn($this->file1->isDir());
        $updatedFile1->method("getUniqueName")->willReturn($this->file1->getUniqueName());

        /** @var FileInterface $updatedFile1 */

        $updatedFiles = [
            $updatedFile1,
        ];
        $alreadyRepo->updateRepository([], $updatedFiles, []);

        $this->assertNotNull($alreadyRepo->findFile($this->file1));
        $this->assertNotEquals($this->file1->getHash(), $alreadyRepo->findFile($this->file1)->getHash());
        $this->assertEquals($updatedFile1->getHash(), $alreadyRepo->findFile($this->file1)->getHash());
        $this->assertEquals($this->file1->isDir(), $alreadyRepo->findFile($this->file1)->isDir());
        $this->assertEquals($this->file1->getUniqueName(), $alreadyRepo->findFile($this->file1)->getUniqueName());
    }

    public function testUpdateRepositoryWithEmptyRepoAndRemovedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->method('createOrUpdate')->willReturn(true);

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);

        $removedFiles = [
            $this->file1,
            $this->file2,
            $this->dir,
        ];

        $this->expectException(LogicException::class);
        $alreadyRepo->updateRepository([], [], $removedFiles);
    }

    public function testUpdateRepositoryWithNotEmptyRepoAndRemovedFiles(): void
    {
        /** @var MockObject $repo */
        $repo = $this->repository;
        $repo->method('findByName')->willReturn($this->metadataFile);

        $repo->method('getContent')->willReturn(serialize([
            $this->file1->getUniqueName() => $this->file1,
            $this->file2->getUniqueName() => $this->file2,
            $this->dir->getUniqueName() => $this->dir,
        ]));

        $repo->expects($this->once())->method('createOrUpdate')->willReturn(true);

        $alreadyRepo = new AlreadySynchronizedRepository($this->repository, $this->filename);
        $removedFiles = [
            $this->file2,
            $this->dir,
        ];
        $alreadyRepo->updateRepository([], [], $removedFiles);

        $this->assertNotNull($alreadyRepo->findFile($this->file1));
        $this->assertNull($alreadyRepo->findFile($this->file2));
        $this->assertNull($alreadyRepo->findFile($this->dir));
    }
}
