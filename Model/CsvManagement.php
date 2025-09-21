<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\FileUploader\Api\FileUploaderManagementInterface;
use EPuzzle\ImportExport\Api\CsvManagementInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;

/**
 * Provides functionality to import/export data using CSV files
 */
class CsvManagement implements CsvManagementInterface
{
    /**
     * CsvManagement
     *
     * @param CsvManagement\Import $import
     * @param CsvManagement\ToBatches $toBatches
     * @param ConfigProvider $configProvider
     * @param FileRepositoryInterface $fileRepository
     * @param FileUploaderManagementInterface $fileUploaderManagement
     */
    public function __construct(
        private CsvManagement\Import $import,
        private CsvManagement\ToBatches $toBatches,
        private readonly ConfigProvider $configProvider,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly FileUploaderManagementInterface $fileUploaderManagement
    ) {
    }

    /**
     * @inheritDoc
     */
    public function import(ImportInterface $import, ?string $fileId = null): array
    {
        $files = $this->toBatches($fileId);

        return $this->import->execute($import, $files);
    }

    /**
     * @inheritDoc
     */
    public function toBatches(?string $fileId = null, ?int $maxSize = null): array
    {
        $maxSize = $maxSize ?: $this->configProvider->csvMaxSize();

        return $this->toBatches->execute($this->resolveFileId($fileId), $maxSize);
    }

    /**
     * Resolve the file ID to import
     *
     * @param string|null $fileId
     * @return int
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws LocalizedException
     */
    private function resolveFileId(?string $fileId = null): int
    {
        if (is_numeric($fileId)) {
            $file = $this->fileRepository->get((int)$fileId);
        } else {
            $fileUploaderSettings = $this->fileUploaderManagement->createFileUploaderSettings();
            $fileUploaderSettings->getExtensionAttributes()->setUploaderAllowedExtensions(['csv']);
            if (!empty($fileId)) {
                $fileUploaderSettings->getExtensionAttributes()->setSystemFilePath($fileId);
            }
            $files = $this->fileUploaderManagement->upload($fileUploaderSettings);
            if (!isset($files[0])) {
                throw new InvalidArgumentException(__('Could not upload the provided file.'));
            }
            $file = $files[0];
        }
        if ($file->getType() !== ConfigProvider::CSV_MIME_TYPE) {
            throw new InvalidArgumentException(__('Provided file is not a CSV file.'));
        }

        return $file->getEntityId();
    }
}
