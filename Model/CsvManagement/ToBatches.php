<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model\CsvManagement;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\ImportExport\Model\ConfigProvider;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\Io\File as IoFile;

/**
 * Used to split CSV file into batches (CSV files)
 */
class ToBatches
{
    /**
     * ToBatches
     *
     * @param DriverInterface $fileDriver
     * @param IoFile $ioFile
     * @param FileRepositoryInterface $fileRepository
     */
    public function __construct(
        private DriverInterface $fileDriver,
        private readonly IoFile $ioFile,
        private readonly FileRepositoryInterface $fileRepository,
    ) {
    }

    /**
     * Split CSV file into batches
     *
     * @param int $fileId
     * @param int $maxSize
     * @return FileInterface[]
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws NoSuchEntityException
     */
    public function execute(int $fileId, int $maxSize = ConfigProvider::CSV_MAX_SIZE): array
    {
        $file = $this->fileRepository->get($fileId);
        if ($file->getSize() <= $maxSize) {
            // exit: this file is smaller than the maximum size, so no need to split it
            return [$file];
        }
        $resourceToRead = $this->fileDriver->fileOpen($file->getFullPath(), 'r');
        $header = $this->fileDriver->fileGetCsv($resourceToRead);
        if (!$header) {
            throw new FileSystemException(__('CSV file is empty'));
        }
        $batchIndex = $batchRowsCount = 0;
        $batchCsvFiles = [];
        [$filePathToWrite, $resourceToWrite] = $this->batchFileOpen($file->getFullPath(), ++$batchIndex);
        while (($row = $this->fileDriver->fileGetCsv($resourceToRead)) !== false) {
            if ($batchRowsCount === 0) {
                $this->fileDriver->filePutCsv($resourceToWrite, $header);
            }
            $this->fileDriver->filePutCsv($resourceToWrite, $row);
            $batchRowsCount++;
            if ($batchRowsCount % 100 === 0
                && $this->fileDriver->fileTell($resourceToWrite) > $maxSize) {
                $batchRowsCount = 0;
                $this->fileDriver->fileClose($resourceToWrite);
                $batchCsvFiles[] = $this->saveFileToDb($filePathToWrite);
                [$filePathToWrite, $resourceToWrite] = $this->batchFileOpen($file->getFullPath(), ++$batchIndex);
            }
        }
        if (is_resource($resourceToWrite)) {
            $this->fileDriver->fileClose($resourceToWrite);
            $batchCsvFiles[] = $this->saveFileToDb($filePathToWrite);
        }
        $this->fileDriver->fileClose($resourceToRead);

        return $batchCsvFiles;
    }

    /**
     * Open batch file to write data
     *
     * @param string $filePath
     * @param int $index
     * @return array[string, resource] returns the path to the file and resource to write data to it
     * @throws FileSystemException
     */
    private function batchFileOpen(string $filePath, int $index): array
    {
        $fileName = $this->ioFile->getPathInfo($filePath)['filename'];
        $batchFilePath = str_replace($fileName, sprintf('%s_%04d', $fileName, $index), $filePath);

        return [$batchFilePath, $this->fileDriver->fileOpen($batchFilePath, 'ab')];
    }

    /**
     * Saves the file to the database
     *
     * @param string $filePath
     * @return FileInterface
     * @throws CouldNotSaveException
     */
    private function saveFileToDb(string $filePath): FileInterface
    {
        $fileInfo = $this->ioFile->getPathInfo($filePath);
        $batchFileName = trim($fileInfo['basename'], DIRECTORY_SEPARATOR);
        $batchFilePath = rtrim($fileInfo['dirname'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $batchCsvFile = $this->fileRepository->create();
        $batchCsvFile->setType(ConfigProvider::CSV_MIME_TYPE);
        $batchCsvFile->setName($batchFileName);
        $batchCsvFile->setPath($batchFilePath);
        $this->fileRepository->save($batchCsvFile);

        return $batchCsvFile;
    }
}
