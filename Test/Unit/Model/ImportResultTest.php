<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model;

use EPuzzle\ImportExport\Model\ImportResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\ImportResult
 */
class ImportResultTest extends TestCase
{
    public function testFileIdAccessors(): void
    {
        $sut = new ImportResult();
        $sut->setFileId(123);
        self::assertSame(123, $sut->getFileId());
    }

    public function testIsSuccessAccessors(): void
    {
        $sut = new ImportResult();
        $sut->setIsSuccess(true);
        self::assertTrue($sut->getIsSuccess());
        $sut->setIsSuccess(false);
        self::assertFalse($sut->getIsSuccess());
    }

    public function testMessagesAccessorsAndAddMessage(): void
    {
        $sut = new ImportResult();
        self::assertSame([], $sut->getMessages());
        $sut->setMessages(['m1', 'm2']);
        self::assertSame(['m1', 'm2'], $sut->getMessages());
        $sut->addMessage('m3');
        self::assertSame(['m1', 'm2', 'm3'], $sut->getMessages());
    }

    public function testLogTraceAccessors(): void
    {
        $sut = new ImportResult();
        $sut->setLogTrace('trace data');
        self::assertSame('trace data', $sut->getLogTrace());
    }

    public function testErrorReportUrlAccessors(): void
    {
        $sut = new ImportResult();
        self::assertNull($sut->getErrorReportUrl());
        $sut->setErrorReportUrl('http://epuzzle.org/report.csv');
        self::assertSame('http://epuzzle.org/report.csv', $sut->getErrorReportUrl());
        $sut->setErrorReportUrl(null);
        self::assertNull($sut->getErrorReportUrl());
    }
}
