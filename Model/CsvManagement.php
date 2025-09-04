<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\FileUploader\Api\FileUploaderManagementInterface;
use EPuzzle\ImportExport\Api\CsvManagementInterface;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Provides functionality to import/export data using CSV files
 */
class CsvManagement implements CsvManagementInterface
{
    /**
     * CsvManagement
     *
     * @param FileUploaderManagementInterface $fileUploaderManagement
     * @param FileRepositoryInterface $fileRepository
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly FileUploaderManagementInterface $fileUploaderManagement,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly DirectoryList $directoryList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function import(
        ?int $fileId = null,
        string $outputFormat = 'json',
        string $entityType = 'catalog_product',
        string $behavior = Import::BEHAVIOR_APPEND,
        ?string $catalogImagesPath = null
    ): string {
        if (!$fileId) {
            try {
                $fileId = $this->uploadCsvFile();
            } catch (Exception $exception) {
                throw new CouldNotSaveException(
                    __('Could not upload the CSV file: %error.', ['error' => $exception->getMessage()]),
                    $exception
                );
            }
        }
        try {
            $file = $this->fileRepository->get($fileId);
            $args = [
                $this->findPhpBinary(),
                '-d', 'max_execution_time=0',
                '-d', 'memory_limit=-1',
                '-d', 'xdebug.mode=off',
                '-d', 'zlib.output_compression=0',
                '-d', 'output_buffering=0',
                '-d', 'zlib.output_handler=',
                $this->directoryList->getRoot() . '/bin/magento',
                'epuzzle:import-export:csv-import',
                '-i', (string)$file->getEntityId(),
                '-e', $entityType,
                '-b', $behavior,
                '-f', $outputFormat
            ];
            if ($catalogImagesPath) {
                $args[] = '--catalog-images-path';
                $args[] = $catalogImagesPath;
            }
            $process = new Process($args);
            $process->run();

            return $process->getOutput();
        } catch (Exception $exception) {
            throw new LocalizedException(__('Could not import this CSV file.'), $exception);
        }
    }

    /**
     * Try to upload the image
     *
     * @return int
     * @throws InvalidArgumentException
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws NotFoundException
     */
    private function uploadCsvFile(): int
    {
        $fileUploaderSettings = $this->fileUploaderManagement->createFileUploaderSettings();
        $fileUploaderSettings->getExtensionAttributes()->setUploaderAllowedExtensions(['csv']);
        $files = $this->fileUploaderManagement->upload($fileUploaderSettings);
        if (!isset($files[0])) {
            throw new InvalidArgumentException(__('The uploaded image is invalid or empty.'));
        }

        return $files[0]->getEntityId();
    }

    /**
     * Find the PHP binary file
     *
     * @return string
     * @throws RuntimeException
     */
    private function findPhpBinary(): string
    {
        $process = new Process(['which', 'php']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException(__('Unable to locate PHP binary.'));
        }

        return trim($process->getOutput());
    }
}
