<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Test\Unit\Model;

use EPuzzle\ImportExport\Model\ConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EPuzzle\ImportExport\Model\ConfigProvider
 */
class ConfigProviderTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;
    /** @var DeploymentConfig&MockObject */
    private $deploymentConfig;
    /** @var ConfigProvider */
    private $sut;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->deploymentConfig = $this->getMockBuilder(DeploymentConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sut = new ConfigProvider(
            $this->scopeConfig,
            $this->deploymentConfig,
        );
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\ConfigProvider::csvMaxSize
     */
    public function testCsvMaxSizeReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('epuzzle_import_export/csv/max_size')
            ->willReturn('12345');
        self::assertSame(12345, $this->sut->csvMaxSize());
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\ConfigProvider::csvMaxSize
     */
    public function testCsvMaxSizeReturnsDefaultWhenConfigEmpty(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('epuzzle_import_export/csv/max_size')
            ->willReturn(null);
        self::assertSame(ConfigProvider::CSV_MAX_SIZE, $this->sut->csvMaxSize());
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\ConfigProvider::securityHash
     */
    public function testSecurityHashSuccess(): void
    {
        $payload = 'payload-value';
        $secret = 'top-secret';
        $this->scopeConfig->method('getValue')
            ->with('epuzzle_import_export/security/key')
            ->willReturn($payload);
        $this->deploymentConfig->method('get')
            ->with('crypt/key')
            ->willReturn($secret);
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        self::assertSame($expected, $this->sut->securityHash());
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\ConfigProvider::securityHash
     */
    public function testSecurityHashBubblesRuntimeException(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('epuzzle_import_export/security/key')
            ->willReturn('p');
        $this->deploymentConfig->method('get')
            ->with('crypt/key')
            ->willThrowException(new RuntimeException(__('rt')));
        $this->expectException(RuntimeException::class);
        $this->sut->securityHash();
    }

    /**
     * @covers \EPuzzle\ImportExport\Model\ConfigProvider::securityHash
     */
    public function testSecurityHashBubblesFileSystemException(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('epuzzle_import_export/security/key')
            ->willReturn('p');
        $this->deploymentConfig->method('get')
            ->with('crypt/key')
            ->willThrowException(new FileSystemException(__('fs')));
        $this->expectException(FileSystemException::class);
        $this->sut->securityHash();
    }
}
