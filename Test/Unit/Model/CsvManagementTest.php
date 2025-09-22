<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\FileUploader\Api\Data\FileUploaderSettingsExtensionInterface;
use EPuzzle\FileUploader\Api\Data\FileUploaderSettingsInterface;
use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\FileUploader\Api\FileUploaderManagementInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterface;
use EPuzzle\ImportExport\Model\ConfigProvider;
use EPuzzle\ImportExport\Model\CsvManagement;
use EPuzzle\ImportExport\Model\CsvManagement\Import as CsvImport;
use EPuzzle\ImportExport\Model\CsvManagement\ToBatches;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\CsvManagement::import
 * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
 */
class CsvManagementTest extends TestCase
{
    /** @var CsvImport&MockObject */
    private $import;
    /** @var ToBatches&MockObject */
    private $toBatches;
    /** @var ConfigProvider&MockObject */
    private $configProvider;
    /** @var FileRepositoryInterface&MockObject */
    private $fileRepository;
    /** @var FileUploaderManagementInterface&MockObject */
    private $fileUploaderManagement;
    /** @var CsvManagement */
    private $sut;

    protected function setUp(): void
    {
        $this->import = $this->getMockBuilder(CsvImport::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->toBatches = $this->getMockBuilder(ToBatches::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configProvider = $this->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileRepository = $this->getMockBuilder(FileRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileUploaderManagement = $this->getMockBuilder(
            FileUploaderManagementInterface::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $this->sut = new CsvManagement(
            $this->import,
            $this->toBatches,
            $this->configProvider,
            $this->fileRepository,
            $this->fileUploaderManagement
        );
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::import
     * @throws LocalizedException
     */
    public function testImportDelegatesToToBatchesAndImport(): void
    {
        $fileIdNumeric = '5';
        $repoFile = $this->createMock(FileInterface::class);
        $repoFile->method('getType')->willReturn(ConfigProvider::CSV_MIME_TYPE);
        $repoFile->method('getEntityId')->willReturn(5);
        $this->fileRepository->expects($this->once())
            ->method('get')
            ->with(5)
            ->willReturn($repoFile);
        $this->configProvider->expects($this->once())
            ->method('csvMaxSize')
            ->willReturn(200);
        $batched = [$this->createMock(FileInterface::class)];
        $this->toBatches->expects($this->once())
            ->method('execute')
            ->with(5, 200)
            ->willReturn($batched);
        $importModel = $this->createMock(ImportInterface::class);
        $expected = ['ok'];
        $this->import->expects($this->once())
            ->method('execute')
            ->with($importModel, $batched)
            ->willReturn($expected);
        $result = $this->sut->import($importModel, $fileIdNumeric);
        self::assertSame($expected, $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     * @throws LocalizedException
     */
    public function testToBatchesResolvesNumericIdAndUsesConfigMax(): void
    {
        $repoFile = $this->createMock(FileInterface::class);
        $repoFile->method('getType')->willReturn(ConfigProvider::CSV_MIME_TYPE);
        $repoFile->method('getEntityId')->willReturn(7);
        $this->fileRepository->expects($this->once())
            ->method('get')
            ->with(7)
            ->willReturn($repoFile);
        $this->configProvider->expects($this->once())
            ->method('csvMaxSize')
            ->willReturn(512);
        $files = [$this->createMock(FileInterface::class)];
        $this->toBatches->expects($this->once())
            ->method('execute')
            ->with(7, 512)
            ->willReturn($files);
        $result = $this->sut->toBatches('7');
        self::assertSame($files, $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     * @throws LocalizedException
     */
    public function testToBatchesUploadsWhenNonNumericAndSetsExtensionsAndSystemPath(): void
    {
        $settings = $this->getMockBuilder(FileUploaderSettingsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extAttrs = $this->getMockBuilder(FileUploaderSettingsExtensionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileUploaderManagement->expects($this->once())
            ->method('createFileUploaderSettings')
            ->willReturn($settings);
        $settings->expects($this->exactly(2))
            ->method('getExtensionAttributes')
            ->willReturn($extAttrs);
        $extAttrs->expects($this->once())
            ->method('setUploaderAllowedExtensions')
            ->with(['csv']);
        $extAttrs->expects($this->once())
            ->method('setSystemFilePath')
            ->with('/tmp/file.csv');
        $uploadedFile = $this->createMock(FileInterface::class);
        $uploadedFile->method('getType')->willReturn(ConfigProvider::CSV_MIME_TYPE);
        $uploadedFile->method('getEntityId')->willReturn(42);
        $this->fileUploaderManagement->expects($this->once())
            ->method('upload')
            ->with($settings)
            ->willReturn([$uploadedFile]);
        $this->configProvider->expects($this->once())
            ->method('csvMaxSize')
            ->willReturn(300);
        $files = [$this->createMock(FileInterface::class)];
        $this->toBatches->expects($this->once())
            ->method('execute')
            ->with(42, 300)
            ->willReturn($files);
        $result = $this->sut->toBatches('/tmp/file.csv');
        self::assertSame($files, $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     * @throws LocalizedException
     */
    public function testToBatchesUploadWithoutSystemPathWhenEmpty(): void
    {
        $settings = $this->getMockBuilder(FileUploaderSettingsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extAttrs = $this->getMockBuilder(FileUploaderSettingsExtensionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileUploaderManagement->method('createFileUploaderSettings')
            ->willReturn($settings);
        $settings->method('getExtensionAttributes')->willReturn($extAttrs);
        $extAttrs->expects($this->once())
            ->method('setUploaderAllowedExtensions')
            ->with(['csv']);
        $extAttrs->expects($this->never())->method('setSystemFilePath');
        $uploadedFile = $this->createMock(FileInterface::class);
        $uploadedFile->method('getType')->willReturn(ConfigProvider::CSV_MIME_TYPE);
        $uploadedFile->method('getEntityId')->willReturn(101);
        $this->fileUploaderManagement->method('upload')
            ->with($settings)
            ->willReturn([$uploadedFile]);
        $this->configProvider->method('csvMaxSize')->willReturn(256);
        $files = [$this->createMock(FileInterface::class)];
        $this->toBatches->method('execute')->with(101, 256)->willReturn($files);
        $result = $this->sut->toBatches('');
        self::assertSame($files, $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     * @throws LocalizedException
     */
    public function testToBatchesThrowsWhenUploadReturnsEmpty(): void
    {
        $settings = $this->getMockBuilder(FileUploaderSettingsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extAttrs = $this->getMockBuilder(FileUploaderSettingsExtensionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileUploaderManagement->method('createFileUploaderSettings')
            ->willReturn($settings);
        $settings->expects($this->exactly(2))
            ->method('getExtensionAttributes')
            ->willReturn($extAttrs);
        $extAttrs->expects($this->once())
            ->method('setUploaderAllowedExtensions')
            ->with(['csv']);
        $extAttrs->expects($this->once())
            ->method('setSystemFilePath')
            ->with('/tmp/file.csv');
        $this->fileUploaderManagement->method('upload')
            ->with($settings)
            ->willReturn([]);
        $this->expectException(InvalidArgumentException::class);
        $this->sut->toBatches('/tmp/file.csv');
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     * @throws LocalizedException
     */
    public function testToBatchesRejectsNonCsvFromRepository(): void
    {
        $bad = $this->createMock(FileInterface::class);
        $bad->method('getType')->willReturn('application/pdf');
        $this->fileRepository->method('get')->with(9)->willReturn($bad);
        $this->configProvider->method('csvMaxSize')->willReturn(512);
        $this->toBatches->expects($this->never())->method('execute');
        $this->expectException(InvalidArgumentException::class);
        $this->sut->toBatches('9');
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     */
    public function testToBatchesBubblesNotFoundFromRepository(): void
    {
        $this->fileRepository->method('get')
            ->with(404)
            ->willThrowException(new NotFoundException(__('nf')));
        $this->configProvider->method('csvMaxSize')->willReturn(128);
        $this->expectException(NotFoundException::class);
        $this->sut->toBatches('404');
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement::toBatches
     */
    public function testToBatchesBubblesCouldNotSaveFromUpload(): void
    {
        $settings = $this->getMockBuilder(FileUploaderSettingsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extAttrs = $this->getMockBuilder(FileUploaderSettingsExtensionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->fileUploaderManagement->method('createFileUploaderSettings')
            ->willReturn($settings);
        $settings->expects($this->exactly(2))
            ->method('getExtensionAttributes')
            ->willReturn($extAttrs);
        $extAttrs->expects($this->once())
            ->method('setUploaderAllowedExtensions')
            ->with(['csv']);
        $extAttrs->expects($this->once())
            ->method('setSystemFilePath')
            ->with('/tmp/f.csv');
        $this->fileUploaderManagement->method('upload')
            ->willThrowException(new CouldNotSaveException(__('err')));
        $this->expectException(CouldNotSaveException::class);
        $this->sut->toBatches('/tmp/f.csv');
    }
}
