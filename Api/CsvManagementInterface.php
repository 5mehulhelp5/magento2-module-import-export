<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Api;

use EPuzzle\ImportExport\Model\Import;

/**
 * Provides functionality to import/export data using CSV files
 */
interface CsvManagementInterface
{
    /**
     * Import using CSV file
     *
     * @param int|null $fileId If the file ID is empty, the script will try to upload the file using $_FILES data
     * @param string $outputFormat
     * @param string $entityType
     * @param string $behavior
     * @param string|null $catalogImagesPath
     * @return string returns the result string from the import process
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function import(
        ?int $fileId = null,
        string $outputFormat = 'json',
        string $entityType = 'catalog_product',
        string $behavior = Import::BEHAVIOR_APPEND,
        ?string $catalogImagesPath = null
    ): string;
}
