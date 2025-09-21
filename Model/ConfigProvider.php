<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;

/**
 * Provides the store configurations for the import/export functionality
 */
class ConfigProvider
{
    public const int CSV_MAX_SIZE = 20000; // 20 Kilobyte
    public const string CSV_MIME_TYPE = 'text/csv';

    /**
     * ConfigProvider
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        protected readonly ScopeConfigInterface $scopeConfig,
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * Gets the maximum size of the CSV file
     *
     * @return int
     */
    public function csvMaxSize(): int
    {
        return (int)($this->scopeConfig->getValue('epuzzle_import_export/csv/max_size') ?: self::CSV_MAX_SIZE);
    }

    /**
     * Gets the security hash
     *
     * @return string
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function securityHash(): string
    {
        $payload = (string)$this->scopeConfig->getValue('epuzzle_import_export/security/key');
        $secret = $this->deploymentConfig->get('crypt/key');

        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }
}
