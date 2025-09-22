<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model;

use EPuzzle\ImportExport\Model\Import;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\ImportExport\Model\Import as DefaultImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\Import::validateAndImportCsv
 * @covers \EPuzzle\ImportExport\Model\Import::getEntity
 * @covers \EPuzzle\ImportExport\Model\Import::setEntity
 * @covers \EPuzzle\ImportExport\Model\Import::getBehavior
 * @covers \EPuzzle\ImportExport\Model\Import::setBehavior
 * @covers \EPuzzle\ImportExport\Model\Import::getCatalogImagesPath
 * @covers \EPuzzle\ImportExport\Model\Import::setCatalogImagesPath
 */
class ImportTest extends TestCase
{
    public function testGetSetEntity(): void
    {
        $data = [];
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        $sut->setEntity('catalog_product');
        self::assertSame('catalog_product', $sut->getEntity());
    }

    public function testGetSetBehaviorValidValues(): void
    {
        $data = [];
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        $valid = [
            DefaultImport::BEHAVIOR_APPEND,
            DefaultImport::BEHAVIOR_ADD_UPDATE,
            DefaultImport::BEHAVIOR_REPLACE,
            DefaultImport::BEHAVIOR_DELETE,
        ];
        foreach ($valid as $behavior) {
            $sut->setBehavior($behavior);
            self::assertSame($behavior, $sut->getBehavior());
        }
    }

    public function testSetBehaviorThrowsOnInvalid(): void
    {
        $data = [];
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        $this->expectException(InvalidArgumentException::class);
        $sut->setBehavior('invalid');
    }

    public function testGetSetCatalogImagesPath(): void
    {
        $data = [];
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        self::assertNull($sut->getCatalogImagesPath());
        $sut->setCatalogImagesPath('pub/media/catalog/product');
        self::assertSame('pub/media/catalog/product', $sut->getCatalogImagesPath());
        $sut->setCatalogImagesPath(null);
        self::assertNull($sut->getCatalogImagesPath());
    }

    public function testValidateAndImportCsvSetsDefaultsAndInvalidatesOnSuccess(): void
    {
        $data = [];
        $filePath = '/tmp/products.csv';
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'hasData',
                'setData',
                'getData',
                '_getSourceAdapter',
                'validateSource',
                'createHistoryReport',
                'getErrorAggregator',
                'importSource',
                'invalidateIndex',
            ])
            ->getMock();
        $sut->method('hasData')->willReturnCallback(function (string $k) use (&$data) {
            return array_key_exists($k, $data);
        });
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        $source = $this->getMockBuilder(
            \Magento\ImportExport\Model\Import\AbstractSource::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $sut->expects($this->once())
            ->method('_getSourceAdapter')
            ->with($filePath)
            ->willReturn($source);
        $sut->expects($this->once())
            ->method('validateSource')
            ->with($source);
        $sut->expects($this->once())
            ->method('createHistoryReport')
            ->with($filePath, $this->isType('string'));
        $aggregator = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $aggregator->method('hasFatalExceptions')->willReturn(false);
        $sut->method('getErrorAggregator')->willReturn($aggregator);
        $sut->method('importSource')->willReturn(true);
        $sut->expects($this->once())->method('invalidateIndex');
        $result = $sut->validateAndImportCsv($filePath);
        self::assertTrue($result);
        self::assertSame('catalog_product', $data['entity']);
        self::assertSame(DefaultImport::BEHAVIOR_APPEND, $data['behavior']);
        self::assertSame('pub/media/catalog/product', $data[DefaultImport::FIELD_NAME_IMG_FILE_DIR]);
        self::assertSame(
            ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS,
            $data[DefaultImport::FIELD_NAME_VALIDATION_STRATEGY]
        );
        self::assertSame(',', $data[DefaultImport::FIELD_FIELD_SEPARATOR]);
        self::assertSame(',', $data[DefaultImport::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR]);
    }

    public function testValidateAndImportCsvKeepsExistingValuesAndNoInvalidateOnFail(): void
    {
        $data = [
            'entity' => 'customer',
            'behavior' => DefaultImport::BEHAVIOR_REPLACE,
            DefaultImport::FIELD_NAME_IMG_FILE_DIR => 'custom/dir',
            DefaultImport::FIELD_NAME_VALIDATION_STRATEGY => 'stop_on_error',
            DefaultImport::FIELD_FIELD_SEPARATOR => ';',
            DefaultImport::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => '|',
        ];
        $filePath = '/tmp/custom.csv';
        /** @var Import&MockObject $sut */
        $sut = $this->getMockBuilder(Import::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'hasData',
                'setData',
                'getData',
                '_getSourceAdapter',
                'validateSource',
                'createHistoryReport',
                'getErrorAggregator',
                'importSource',
                'invalidateIndex',
            ])
            ->getMock();
        $sut->method('hasData')->willReturnCallback(function (string $k) use (&$data) {
            return array_key_exists($k, $data);
        });
        $sut->method('setData')->willReturnCallback(function (string $k, $v) use (&$data) {
            $data[$k] = $v;

            return null;
        });
        $sut->method('getData')->willReturnCallback(function (string $k) use (&$data) {
            return $data[$k] ?? null;
        });
        $source = $this->getMockBuilder(
            \Magento\ImportExport\Model\Import\AbstractSource::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $sut->method('_getSourceAdapter')->with($filePath)->willReturn($source);
        $sut->expects($this->once())->method('validateSource')->with($source);
        $sut->expects($this->once())->method('createHistoryReport')->with($filePath, 'customer');
        $aggregator = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $aggregator->method('hasFatalExceptions')->willReturn(true);
        $sut->method('getErrorAggregator')->willReturn($aggregator);
        $sut->method('importSource')->willReturn(false);
        $sut->expects($this->never())->method('invalidateIndex');
        $result = $sut->validateAndImportCsv($filePath);
        self::assertFalse($result);
        self::assertSame('customer', $data['entity']);
        self::assertSame(DefaultImport::BEHAVIOR_REPLACE, $data['behavior']);
        self::assertSame('custom/dir', $data[DefaultImport::FIELD_NAME_IMG_FILE_DIR]);
        self::assertSame('stop_on_error', $data[DefaultImport::FIELD_NAME_VALIDATION_STRATEGY]);
        self::assertSame(';', $data[DefaultImport::FIELD_FIELD_SEPARATOR]);
        self::assertSame('|', $data[DefaultImport::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR]);
    }
}
