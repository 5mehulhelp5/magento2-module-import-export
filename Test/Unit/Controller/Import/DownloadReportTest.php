<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Controller\Import;

use EPuzzle\ImportExport\Controller\Import\DownloadReport;
use EPuzzle\ImportExport\Model\ConfigProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\ImportExport\Helper\Report;
use Magento\ImportExport\Model\Import;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Controller\Import\DownloadReport
 */
class DownloadReportTest extends TestCase
{
    /**
     * @var RequestInterface&MockObject
     */
    private $request;
    /**
     * @var FileFactory&MockObject
     */
    private $fileFactory;
    /**
     * @var Report&MockObject
     */
    private $reportHelper;
    /**
     * @var ConfigProvider&MockObject
     */
    private $configProvider;
    /**
     * @var DownloadReport
     */
    private $sut;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->fileFactory = $this->getMockBuilder(FileFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->reportHelper = $this->getMockBuilder(Report::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $objectManager = new ObjectManager($this);
        $this->sut = $objectManager->getObject(
            DownloadReport::class,
            [
                'request' => $this->request,
                'resultFileFactory' => $this->fileFactory,
                'reportHelper' => $this->reportHelper,
                'configProvider' => $this->configProvider
            ]
        );
        self::assertInstanceOf(HttpGetActionInterface::class, $this->sut);
    }

    /**
     * @covers \EPuzzle\ImportExport\Controller\Import\DownloadReport::execute
     */
    public function testExecuteSuccessReturnsResponseAndUsesBasename(): void
    {
        $validHash = 'secure-hash';
        $rawFileName = '../var/../history/foo.csv'; // должен быть приведён к basename
        $baseName = 'foo.csv';
        $size = 12345;
        $this->configProvider
            ->expects($this->once())
            ->method('securityHash')
            ->willReturn($validHash);
        $this->request
            ->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnMap([
                ['hash', null, $validHash],
                ['fileName', null, $rawFileName],
            ]);
        $this->reportHelper
            ->expects($this->once())
            ->method('importFileExists')
            ->with($baseName)
            ->willReturn(true);
        $this->reportHelper
            ->expects($this->once())
            ->method('getReportSize')
            ->with($baseName)
            ->willReturn($size);
        $expectedPath = Import::IMPORT_HISTORY_DIR . $baseName;
        $response = $this->createMock(ResponseInterface::class);
        $this->fileFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                $baseName,
                ['type' => 'filename', 'value' => $expectedPath],
                DirectoryList::VAR_IMPORT_EXPORT,
                'application/octet-stream',
                $size
            )
            ->willReturn($response);
        // Act
        $result = $this->sut->execute();
        // Assert
        self::assertSame($response, $result);
    }

    /**
     * @covers       \EPuzzle\ImportExport\Controller\Import\DownloadReport::execute
     * @dataProvider dataProviderForNotFoundCases
     */
    public function testExecuteThrowsNotFoundExceptionOnInvalidInput(
        string $configHash,
        string $requestHash,
        string $fileName,
        bool $fileExistsShouldBeCalled,
        ?bool $fileExistsReturn
    ): void {
        // Arrange
        $this->configProvider
            ->expects($this->once())
            ->method('securityHash')
            ->willReturn($configHash);
        $this->request
            ->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnMap([
                ['hash', null, $requestHash],
                ['fileName', null, $fileName],
            ]);
        if ($fileExistsShouldBeCalled) {
            $this->reportHelper
                ->expects($this->once())
                ->method('importFileExists')
                ->with($fileName === '' ? '' : basename($fileName))
                ->willReturn((bool)$fileExistsReturn);
        } else {
            $this->reportHelper
                ->expects($this->never())
                ->method('importFileExists');
        }
        $this->reportHelper
            ->expects($this->never())
            ->method('getReportSize');
        $this->fileFactory
            ->expects($this->never())
            ->method('create');
        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('File not found.');
        // Act
        $this->sut->execute();
    }

    /**
     * Gets data for the not found cases
     *
     * @return array[]
     */
    public function dataProviderForNotFoundCases(): array
    {
        return [
            'invalid hash' => [
                'configHash' => 'expected',
                'requestHash' => 'wrong',
                'fileName' => 'foo.csv',
                'fileExistsShouldBeCalled' => false,
                'fileExistsReturn' => null
            ],
            'empty filename' => [
                'configHash' => 'expected',
                'requestHash' => 'expected',
                'fileName' => '',
                'fileExistsShouldBeCalled' => false,
                'fileExistsReturn' => null
            ],
            'file does not exist' => [
                'configHash' => 'expected',
                'requestHash' => 'expected',
                'fileName' => 'bar.csv',
                'fileExistsShouldBeCalled' => true,
                'fileExistsReturn' => false
            ],
        ];
    }

    /**
     * @covers \EPuzzle\ImportExport\Controller\Import\DownloadReport::execute
     */
    public function testExecuteBubblesUpRuntimeExceptionFromFileFactory(): void
    {
        $validHash = 'secure-hash';
        $fileName = 'ok.csv';
        $size = 10;
        $this->configProvider->method('securityHash')->willReturn($validHash);
        $this->request->method('getParam')->willReturnMap([
            ['hash', null, $validHash],
            ['fileName', null, $fileName],
        ]);
        $this->reportHelper->method('importFileExists')->with($fileName)->willReturn(true);
        $this->reportHelper->method('getReportSize')->with($fileName)->willReturn($size);
        $this->fileFactory
            ->method('create')
            ->willThrowException(new RuntimeException(new Phrase('runtime')));
        $this->expectException(RuntimeException::class);
        $this->sut->execute();
    }

    /**
     * @covers \EPuzzle\ImportExport\Controller\Import\DownloadReport::execute
     */
    public function testExecuteBubblesUpFileSystemExceptionFromFileFactory(): void
    {
        $validHash = 'secure-hash';
        $fileName = 'ok.csv';
        $size = 10;
        $this->configProvider->method('securityHash')->willReturn($validHash);
        $this->request->method('getParam')->willReturnMap([
            ['hash', null, $validHash],
            ['fileName', null, $fileName],
        ]);
        $this->reportHelper->method('importFileExists')->with($fileName)->willReturn(true);
        $this->reportHelper->method('getReportSize')->with($fileName)->willReturn($size);
        $this->fileFactory
            ->method('create')
            ->willThrowException(new FileSystemException(new Phrase('fs')));
        $this->expectException(FileSystemException::class);
        $this->sut->execute();
    }
}
