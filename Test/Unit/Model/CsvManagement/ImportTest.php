<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model\CsvManagement;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterfaceFactory;
use EPuzzle\ImportExport\Api\Data\ImportResultInterface;
use EPuzzle\ImportExport\Api\Data\ImportResultInterfaceFactory;
use EPuzzle\ImportExport\Model\ConfigProvider;
use EPuzzle\ImportExport\Model\CsvManagement\Import;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\Import\RenderErrorMessages;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import
 * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
 */
class ImportTest extends TestCase
{
    /** @var ImportInterfaceFactory&MockObject */
    private $importFactory;
    /** @var RenderErrorMessages&MockObject */
    private $renderErrorMessages;
    /** @var ImportResultInterfaceFactory&MockObject */
    private $importResultFactory;
    /** @var UrlInterface&MockObject */
    private $url;
    /** @var ConfigProvider&MockObject */
    private $configProvider;
    /** @var Import */
    private $sut;
    private const IMPORT_INTERFACE_METHODS = [
        'setEntity',
        'getEntity',
        'setBehavior',
        'getBehavior',
        'setCatalogImagesPath',
        'getCatalogImagesPath',
        'validateAndImportCsv'
    ];

    protected function setUp(): void
    {
        $this->importFactory = $this->getMockBuilder(ImportInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->renderErrorMessages = $this->getMockBuilder(RenderErrorMessages::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->importResultFactory = $this->getMockBuilder(ImportResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->url = $this->createMock(UrlInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->sut = new Import(
            $this->importFactory,
            $this->renderErrorMessages,
            $this->importResultFactory,
            $this->url,
            $this->configProvider,
        );
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     */
    public function testExecuteWithEmptyFilesReturnsEmptyArray(): void
    {
        $import = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $this->importFactory->expects($this->never())->method('create');
        $this->importResultFactory->expects($this->never())->method('create');
        $this->renderErrorMessages->expects($this->never())->method('createErrorReport');
        $this->url->expects($this->never())->method('getUrl');
        $result = $this->sut->execute($import, []);
        self::assertSame([], $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     */
    public function testExecuteSuccessSingleFile(): void
    {
        $originalImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $originalAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $originalImport->method('getErrorAggregator')->willReturn($originalAgg);
        $originalImport->method('getEntity')->willReturn('catalog_product');
        $originalImport->method('getBehavior')->willReturn('append');
        $originalImport->method('getCatalogImagesPath')->willReturn('/var/import/images/');
        $copiedImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $copiedAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $this->importFactory->expects($this->once())->method('create')->willReturn($copiedImport);
        $copiedImport->expects($this->once())
            ->method('setEntity')
            ->with('catalog_product')
            ->willReturnSelf();
        $copiedImport->expects($this->once())
            ->method('setBehavior')
            ->with('append')
            ->willReturnSelf();
        $copiedImport->expects($this->once())
            ->method('setCatalogImagesPath')
            ->with('/var/import/images/')
            ->willReturnSelf();
        $file = $this->createMock(FileInterface::class);
        $file->method('getFullPath')->willReturn('/tmp/file1.csv');
        $file->method('getEntityId')->willReturn(10);
        $copiedImport->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/file1.csv')
            ->willReturn(true);
        $originalAgg->method('hasToBeTerminated')->willReturn(false);
        $copiedImport->method('getFormatedLogTrace')->willReturn('log-trace-1');
        $copiedImport->method('getErrorAggregator')->willReturn($copiedAgg);
        $resultModel = $this->createMock(ImportResultInterface::class);
        $this->importResultFactory->expects($this->once())->method('create')->willReturn($resultModel);
        $resultModel->expects($this->once())->method('setFileId')->with(10)->willReturnSelf();
        $resultModel->expects($this->once())->method('setIsSuccess')->with(true)->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('addMessage')
            ->with($this->isType('string'))
            ->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('setLogTrace')
            ->with('log-trace-1')
            ->willReturnSelf();
        $resultModel->expects($this->never())->method('setErrorReportUrl');
        $this->renderErrorMessages->expects($this->never())->method('createErrorReport');
        $this->url->expects($this->never())->method('getUrl');
        $result = $this->sut->execute($originalImport, [$file]);
        self::assertCount(1, $result);
        self::assertSame($resultModel, $result[0]);
    }

    /**
     * @covers       \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     * @dataProvider failureCombinationsProvider
     */
    public function testExecuteFailureBranchCreatesErrorReport(bool $validateOk, bool $terminated): void
    {
        $originalImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $originalAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $originalImport->method('getErrorAggregator')->willReturn($originalAgg);
        $originalImport->method('getEntity')->willReturn('catalog_category');
        $originalImport->method('getBehavior')->willReturn('replace');
        $originalImport->method('getCatalogImagesPath')->willReturn(null);
        $copiedImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $copiedAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $this->importFactory->expects($this->once())->method('create')->willReturn($copiedImport);
        $copiedImport->expects($this->once())
            ->method('setEntity')
            ->with('catalog_category')
            ->willReturnSelf();
        $copiedImport->expects($this->once())
            ->method('setBehavior')
            ->with('replace')
            ->willReturnSelf();
        $copiedImport->expects($this->never())->method('setCatalogImagesPath');
        $file = $this->createMock(FileInterface::class);
        $file->method('getFullPath')->willReturn('/tmp/file2.csv');
        $file->method('getEntityId')->willReturn(20);
        $copiedImport->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/file2.csv')
            ->willReturn($validateOk);
        $originalAgg->method('hasToBeTerminated')->willReturn($terminated);
        $copiedImport->method('getFormatedLogTrace')->willReturn('log-trace-2');
        $copiedImport->method('getErrorAggregator')->willReturn($copiedAgg);
        $resultModel = $this->createMock(ImportResultInterface::class);
        $this->importResultFactory->expects($this->once())->method('create')->willReturn($resultModel);
        $resultModel->expects($this->once())->method('setFileId')->with(20)->willReturnSelf();
        $resultModel->expects($this->once())->method('setIsSuccess')->with(false)->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('addMessage')
            ->with($this->isType('string'))
            ->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('setLogTrace')
            ->with('log-trace-2')
            ->willReturnSelf();
        $this->configProvider->expects($this->once())->method('securityHash')->willReturn('h123');
        $this->renderErrorMessages->expects($this->once())
            ->method('createErrorReport')
            ->with($copiedAgg)
            ->willReturn('report.csv');
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with(
                'epuzzleImportExport/import/downloadReport',
                ['fileName' => 'report.csv', 'hash' => 'h123']
            )
            ->willReturn('http://epuzzle.org/report.csv');
        $resultModel->expects($this->once())
            ->method('setErrorReportUrl')
            ->with('http://epuzzle.org/report.csv')
            ->willReturnSelf();
        $result = $this->sut->execute($originalImport, [$file]);
        self::assertCount(1, $result);
        self::assertSame($resultModel, $result[0]);
    }

    public function failureCombinationsProvider(): array
    {
        return [
            'validate failed' => [false, false],
            'terminated true' => [true, true],
        ];
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     */
    public function testExecuteMultipleFilesAggregateResults(): void
    {
        $originalImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $originalAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $originalImport->method('getErrorAggregator')->willReturn($originalAgg);
        $originalImport->method('getEntity')->willReturn('customer');
        $originalImport->method('getBehavior')->willReturn('append');
        $originalImport->method('getCatalogImagesPath')->willReturn('');
        $copiedImport1 = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $copiedImport2 = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $copiedAgg1 = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $copiedAgg2 = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $this->importFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($copiedImport1, $copiedImport2);
        $copiedImport1->expects($this->once())
            ->method('setEntity')
            ->with('customer')
            ->willReturnSelf();
        $copiedImport1->expects($this->once())
            ->method('setBehavior')
            ->with('append')
            ->willReturnSelf();
        $copiedImport1->expects($this->never())
            ->method('setCatalogImagesPath');
        $copiedImport2->expects($this->once())
            ->method('setEntity')
            ->with('customer')
            ->willReturnSelf();
        $copiedImport2->expects($this->once())
            ->method('setBehavior')
            ->with('append')
            ->willReturnSelf();
        $copiedImport2->expects($this->never())->method('setCatalogImagesPath');
        $file1 = $this->createMock(FileInterface::class);
        $file2 = $this->createMock(FileInterface::class);
        $file1->method('getFullPath')->willReturn('/tmp/a.csv');
        $file1->method('getEntityId')->willReturn(1);
        $file2->method('getFullPath')->willReturn('/tmp/b.csv');
        $file2->method('getEntityId')->willReturn(2);
        $copiedImport1->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/a.csv')
            ->willReturn(true);
        $copiedImport2->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/b.csv')
            ->willReturn(false);
        $originalAgg->method('hasToBeTerminated')->willReturn(false);
        $copiedImport1->method('getFormatedLogTrace')->willReturn('lt1');
        $copiedImport2->method('getFormatedLogTrace')->willReturn('lt2');
        $copiedImport1->method('getErrorAggregator')->willReturn($copiedAgg1);
        $copiedImport2->method('getErrorAggregator')->willReturn($copiedAgg2);
        $resultOk = $this->createMock(ImportResultInterface::class);
        $resultFail = $this->createMock(ImportResultInterface::class);
        $this->importResultFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($resultOk, $resultFail);
        $resultOk->expects($this->once())
            ->method('setFileId')->with(1)->willReturnSelf();
        $resultOk->expects($this->once())
            ->method('setIsSuccess')->with(true)->willReturnSelf();
        $resultOk->expects($this->once())
            ->method('addMessage')->with($this->isType('string'))->willReturnSelf();
        $resultOk->expects($this->once())
            ->method('setLogTrace')->with('lt1')->willReturnSelf();
        $resultOk->expects($this->never())
            ->method('setErrorReportUrl');
        $resultFail->expects($this->once())
            ->method('setFileId')->with(2)->willReturnSelf();
        $resultFail->expects($this->once())
            ->method('setIsSuccess')->with(false)->willReturnSelf();
        $resultFail->expects($this->once())
            ->method('addMessage')->with($this->isType('string'))->willReturnSelf();
        $resultFail->expects($this->once())
            ->method('setLogTrace')->with('lt2')->willReturnSelf();
        $this->configProvider->expects($this->once())->method('securityHash')->willReturn('hs');
        $this->renderErrorMessages->expects($this->once())
            ->method('createErrorReport')
            ->with($copiedAgg2)
            ->willReturn('err.csv');
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with('epuzzleImportExport/import/downloadReport', ['fileName' => 'err.csv', 'hash' => 'hs'])
            ->willReturn('http://example/err.csv');
        $resultFail->expects($this->once())
            ->method('setErrorReportUrl')
            ->with('http://example/err.csv')
            ->willReturnSelf();
        $result = $this->sut->execute($originalImport, [$file1, $file2]);
        self::assertSame([$resultOk, $resultFail], $result);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     */
    public function testExecuteBubblesLocalizedExceptionFromValidate(): void
    {
        $originalImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $originalAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $originalImport->method('getErrorAggregator')->willReturn($originalAgg);
        $originalImport->method('getEntity')->willReturn('entity');
        $originalImport->method('getBehavior')->willReturn('append');
        $originalImport->method('getCatalogImagesPath')->willReturn(null);
        $copiedImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $this->importFactory->expects($this->once())->method('create')->willReturn($copiedImport);
        $file = $this->createMock(FileInterface::class);
        $file->method('getFullPath')->willReturn('/tmp/x.csv');
        $file->method('getEntityId')->willReturn(99);
        $copiedImport->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/x.csv')
            ->willThrowException(new LocalizedException(__('validation error')));
        $this->expectException(LocalizedException::class);
        $this->sut->execute($originalImport, [$file]);
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\CsvManagement\Import::execute
     */
    public function testExecuteBubblesFileSystemExceptionFromCreateErrorReport(): void
    {
        $originalImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $originalAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $originalImport->method('getErrorAggregator')->willReturn($originalAgg);
        $originalImport->method('getEntity')->willReturn('entity');
        $originalImport->method('getBehavior')->willReturn('append');
        $originalImport->method('getCatalogImagesPath')->willReturn(null);
        $copiedImport = $this->getMockBuilder(ImportInterface::class)
            ->onlyMethods(self::IMPORT_INTERFACE_METHODS)
            ->addMethods(['getErrorAggregator', 'getFormatedLogTrace'])
            ->getMock();
        $copiedAgg = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $this->importFactory->expects($this->once())->method('create')->willReturn($copiedImport);
        $file = $this->createMock(FileInterface::class);
        $file->method('getFullPath')->willReturn('/tmp/x.csv');
        $file->method('getEntityId')->willReturn(77);
        $copiedImport->expects($this->once())
            ->method('validateAndImportCsv')
            ->with('/tmp/x.csv')
            ->willReturn(false);
        $originalAgg->method('hasToBeTerminated')->willReturn(false);
        $copiedImport->method('getFormatedLogTrace')->willReturn('trace');
        $copiedImport->method('getErrorAggregator')->willReturn($copiedAgg);
        $resultModel = $this->createMock(ImportResultInterface::class);
        $this->importResultFactory->expects($this->once())->method('create')->willReturn($resultModel);
        $resultModel->expects($this->once())
            ->method('setFileId')
            ->with(77)
            ->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('setIsSuccess')
            ->with(false)
            ->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('addMessage')
            ->with($this->isType('string'))
            ->willReturnSelf();
        $resultModel->expects($this->once())
            ->method('setLogTrace')
            ->with('trace')
            ->willReturnSelf();
        $this->renderErrorMessages->expects($this->once())
            ->method('createErrorReport')
            ->with($copiedAgg)
            ->willThrowException(new FileSystemException(__('fs error')));
        $this->expectException(FileSystemException::class);
        $this->sut->execute($originalImport, [$file]);
    }
}
