<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Api;

use EPuzzle\ImportExport\Api\Data\ImportInterface;

/**
 * Provides functionality to import/export data using CSV files
 */
interface CsvManagementInterface
{
    /**
     * Import using CSV file
     *
     * @param \EPuzzle\ImportExport\Api\Data\ImportInterface $import
     * @param string|null $fileId If the file ID is empty, the script will try to upload the file using $_FILES data
     * @return \EPuzzle\ImportExport\Api\Data\ImportResultInterface[]
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InvalidArgumentException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function import(ImportInterface $import, ?string $fileId = null): array;

    /**
     * Split CSV file into batches
     *
     * @param string|null $fileId If the file ID is empty, the script will try to upload the file using $_FILES data
     * @param int|null $maxSize
     * @return \EPuzzle\FileUploader\Api\Data\FileInterface[] returns the list of files
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InvalidArgumentException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function toBatches(?string $fileId = null, ?int $maxSize = null): array;
}
