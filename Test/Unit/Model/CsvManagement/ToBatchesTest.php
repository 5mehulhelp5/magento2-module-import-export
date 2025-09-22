<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model\CsvManagement;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\ImportExport\Model\ConfigProvider;
use EPuzzle\ImportExport\Model\CsvManagement\ToBatches;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\Io\File as IoFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\CsvManagement\ToBatches::execute
 */
class ToBatchesTest extends TestCase
{
    /** @var DriverInterface&MockObject */
    private $driver;
    /** @var IoFile&MockObject */
    private $ioFile;
    /** @var FileRepositoryInterface&MockObject */
    private $fileRepository;
    /** @var ToBatches */
    private $sut;

    protected function setUp(): void
    {
        $this->driver = $this->getMockBuilder(DriverInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->ioFile = $this->getMockBuilder(IoFile::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileRepository = $this->getMockBuilder(FileRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sut = new ToBatches(
            $this->driver,
            $this->ioFile,
            $this->fileRepository
        );
    }

    public function testExecuteReturnsOriginalFileWhenSmallerThanMax(): void
    {
        $fileId = 10;
        $file = $this->createMock(FileInterface::class);
        $file->method('getSize')->willReturn(100);
        $file->method('getFullPath')->willReturn('/var/import/file.csv');
        $this->fileRepository->expects($this->once())
            ->method('get')
            ->with($fileId)
            ->willReturn($file);
        $this->driver->expects($this->never())->method('fileOpen');
        $this->driver->expects($this->never())->method('fileGetCsv');
        $result = $this->sut->execute($fileId, 200);
        self::assertSame([$file], $result);
    }

    public function testExecuteThrowsWhenCannotOpenSource(): void
    {
        $fileId = 11;
        $file = $this->createMock(FileInterface::class);
        $file->method('getSize')->willReturn(1000);
        $file->method('getFullPath')->willReturn('/var/import/file.csv');
        $this->fileRepository->method('get')
            ->with($fileId)
            ->willReturn($file);
        $this->driver->expects($this->once())
            ->method('fileOpen')
            ->with('/var/import/file.csv', 'r')
            ->willReturn(false);
        $this->expectException(FileSystemException::class);
        $this->sut->execute($fileId, 200);
    }

    public function testExecuteThrowsWhenHeaderIsEmpty(): void
    {
        $fileId = 12;
        $file = $this->createMock(FileInterface::class);
        $file->method('getSize')->willReturn(1000);
        $file->method('getFullPath')->willReturn('/var/import/file.csv');
        $this->fileRepository->method('get')->willReturn($file);
        $read = fopen('php://temp', 'w+');
        $this->driver->method('fileOpen')
            ->with('/var/import/file.csv', 'r')
            ->willReturn($read);
        $this->driver->expects($this->once())
            ->method('fileGetCsv')
            ->with($read)
            ->willReturn(false);
        $this->expectException(FileSystemException::class);
        try {
            $this->sut->execute($fileId, 200);
        } finally {
            if (is_resource($read)) {
                fclose($read);
            }
        }
    }

    public function testExecuteSplitsIntoTwoBatchesAndSaves(): void
    {
        $fileId = 13;
        $maxSize = 200;
        $srcPath = '/var/import/file.csv';
        $batch1Path = '/var/import/file_0001.csv';
        $batch2Path = '/var/import/file_0002.csv';
        $srcFile = $this->createMock(FileInterface::class);
        $srcFile->method('getSize')->willReturn(10_000);
        $srcFile->method('getFullPath')->willReturn($srcPath);
        $this->fileRepository->method('get')
            ->with($fileId)
            ->willReturn($srcFile);
        $this->ioFile->method('getPathInfo')->willReturnMap([
            [
                $srcPath,
                ['filename' => 'file', 'basename' => 'file.csv', 'dirname' => '/var/import']
            ],
            [
                $batch1Path,
                ['basename' => 'file_0001.csv', 'dirname' => '/var/import']
            ],
            [
                $batch2Path,
                ['basename' => 'file_0002.csv', 'dirname' => '/var/import']
            ],
        ]);
        $read = fopen('php://temp', 'w+');
        $write1 = fopen('php://temp', 'w+');
        $write2 = fopen('php://temp', 'w+');
        $this->driver->expects($this->exactly(3))
            ->method('fileOpen')
            ->willReturnCallback(
                function (
                    string $path,
                    string $mode
                ) use (
                    $srcPath,
                    $batch1Path,
                    $batch2Path,
                    $read,
                    $write1,
                    $write2
                ) {
                    if ($path === $srcPath && $mode === 'r') {
                        return $read;
                    }
                    if ($path === $batch1Path && $mode === 'ab') {
                        return $write1;
                    }
                    if ($path === $batch2Path && $mode === 'ab') {
                        return $write2;
                    }

                    return false;
                }
            );
        $totalRows = 150;
        $readCounter = 0;
        $this->driver->expects($this->exactly(1 + $totalRows + 1))
            ->method('fileGetCsv')
            ->willReturnCallback(
                function ($res) use (&$readCounter) {
                    if ($readCounter === 0) {
                        $readCounter++;

                        return ['col1', 'col2'];
                    }
                    if ($readCounter <= 150) {
                        $readCounter++;

                        return ['v1', 'v2'];
                    }

                    return false;
                }
            );
        $this->driver->expects($this->any())
            ->method('filePutCsv')
            ->willReturn(null);
        $this->driver->expects($this->once())
            ->method('fileTell')
            ->with($write1)
            ->willReturn($maxSize + 1);
        $closeIndex = 0;
        $closeOrder = [$write1, $write2, $read];
        $this->driver->expects($this->exactly(3))
            ->method('fileClose')
            ->willReturnCallback(
                function ($handle) use (&$closeIndex, $closeOrder) {
                    TestCase::assertSame($closeOrder[$closeIndex], $handle);
                    $closeIndex++;

                    return null;
                }
            );
        $batchEntity1 = $this->createMock(FileInterface::class);
        $batchEntity2 = $this->createMock(FileInterface::class);
        $this->fileRepository->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($batchEntity1, $batchEntity2);
        $batchEntity1->expects($this->once())
            ->method('setType')
            ->with(ConfigProvider::CSV_MIME_TYPE)
            ->willReturnSelf();
        $batchEntity1->expects($this->once())
            ->method('setName')
            ->with('file_0001.csv')
            ->willReturnSelf();
        $batchEntity1->expects($this->once())
            ->method('setPath')
            ->with('/var/import/')
            ->willReturnSelf();
        $batchEntity2->expects($this->once())
            ->method('setType')
            ->with(ConfigProvider::CSV_MIME_TYPE)
            ->willReturnSelf();
        $batchEntity2->expects($this->once())
            ->method('setName')
            ->with('file_0002.csv')
            ->willReturnSelf();
        $batchEntity2->expects($this->once())
            ->method('setPath')
            ->with('/var/import/')
            ->willReturnSelf();
        $saveIndex = 0;
        $expectedSaves = [$batchEntity1, $batchEntity2];
        $this->fileRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(
                function ($entity) use (&$saveIndex, $expectedSaves) {
                    TestCase::assertSame($expectedSaves[$saveIndex], $entity);
                    $saveIndex++;

                    return $saveIndex;
                }
            );
        $result = null;
        try {
            $result = $this->sut->execute($fileId, $maxSize);
        } finally {
            foreach ([$read, $write1, $write2] as $res) {
                if (is_resource($res)) {
                    fclose($res);
                }
            }
        }
        self::assertSame([$batchEntity1, $batchEntity2], $result);
    }

    public function testExecuteBubblesCouldNotSaveException(): void
    {
        $fileId = 14;
        $maxSize = 200;
        $srcPath = '/var/import/file.csv';
        $batch1Path = '/var/import/file_0001.csv';
        $srcFile = $this->createMock(FileInterface::class);
        $srcFile->method('getSize')->willReturn(10_000);
        $srcFile->method('getFullPath')->willReturn($srcPath);
        $this->fileRepository->method('get')->willReturn($srcFile);
        $this->ioFile->method('getPathInfo')->willReturnMap([
            [
                $srcPath,
                ['filename' => 'file', 'basename' => 'file.csv', 'dirname' => '/var/import']
            ],
            [
                $batch1Path,
                ['basename' => 'file_0001.csv', 'dirname' => '/var/import']
            ],
        ]);
        $read = fopen('php://temp', 'w+');
        $write1 = fopen('php://temp', 'w+');
        $this->driver->method('fileOpen')->willReturnCallback(
            function (string $path, string $mode) use ($srcPath, $batch1Path, $read, $write1) {
                if ($path === $srcPath && $mode === 'r') {
                    return $read;
                }
                if ($path === $batch1Path && $mode === 'ab') {
                    return $write1;
                }

                return false;
            }
        );
        $totalRows = 100;
        $readCounter = 0;
        $this->driver->method('fileGetCsv')->willReturnCallback(
            function ($res) use (&$readCounter) {
                if ($readCounter === 0) {
                    $readCounter++;

                    return ['col1'];
                }
                if ($readCounter <= 100) {
                    $readCounter++;

                    return ['v1'];
                }

                return false;
            }
        );
        $this->driver->method('filePutCsv')->willReturn(null);
        $this->driver->method('fileTell')->willReturn($maxSize + 1);
        $entity = $this->createMock(FileInterface::class);
        $this->fileRepository->method('create')->willReturn($entity);
        $this->fileRepository->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willThrowException(new CouldNotSaveException(__('save error')));
        $this->expectException(CouldNotSaveException::class);
        try {
            $this->sut->execute($fileId, $maxSize);
        } finally {
            foreach ([$read, $write1] as $res) {
                if (is_resource($res)) {
                    fclose($res);
                }
            }
        }
    }
}
