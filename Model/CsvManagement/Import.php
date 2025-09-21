<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model\CsvManagement;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterfaceFactory;
use EPuzzle\ImportExport\Api\Data\ImportResultInterface;
use EPuzzle\ImportExport\Api\Data\ImportResultInterfaceFactory;
use EPuzzle\ImportExport\Model\ConfigProvider;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\ImportExport\Model\Import\RenderErrorMessages;

/**
 * Used to import data using CSV file
 */
class Import
{
    /**
     * Import
     *
     * @param ImportInterfaceFactory $importFactory
     * @param RenderErrorMessages $renderErrorMessages
     * @param ImportResultInterfaceFactory $importResultFactory
     * @param UrlInterface $url
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        private readonly ImportInterfaceFactory $importFactory,
        private readonly RenderErrorMessages $renderErrorMessages,
        private readonly ImportResultInterfaceFactory $importResultFactory,
        private readonly UrlInterface $url,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Import data from CSV files
     *
     * @param ImportInterface $import
     * @param FileInterface[] $files
     * @return ImportResultInterface[]
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function execute(ImportInterface $import, array $files): array
    {
        $importResults = [];
        foreach ($files as $file) {
            // import data from the CSV file
            $fileImport = $this->copyImport($import);
            $isSuccess = $fileImport->validateAndImportCsv($file->getFullPath())
                && !$import->getErrorAggregator()->hasToBeTerminated();
            // create the import result
            $fileImportResult = $this->importResultFactory->create();
            $fileImportResult->setFileId($file->getEntityId());
            $fileImportResult->setIsSuccess($isSuccess);
            $fileImportResult->addMessage(
                $isSuccess
                    ? __('The import was successful')->render()
                    : __('The import failed')->render()
            );
            $fileImportResult->setLogTrace($fileImport->getFormatedLogTrace());
            if (!$isSuccess) {
                $errorReportFileName = $this->renderErrorMessages->createErrorReport($fileImport->getErrorAggregator());
                $fileImportResult->setErrorReportUrl(
                    $this->url->getUrl(
                        'epuzzleImportExport/import/downloadReport',
                        ['fileName' => $errorReportFileName, 'hash' => $this->configProvider->securityHash()]
                    )
                );
            }
            $importResults[] = $fileImportResult;
        }

        return $importResults;
    }

    /**
     * Copy the import model
     *
     * @param ImportInterface $import
     * @return ImportInterface
     * @throws InvalidArgumentException
     */
    private function copyImport(ImportInterface $import): ImportInterface
    {
        $copiedImport = $this->importFactory->create();
        $copiedImport->setEntity($import->getEntity());
        $copiedImport->setBehavior($import->getBehavior());
        $copiedImport->setOutputFormat($import->getOutputFormat());
        if ($cImagesPath = $import->getCatalogImagesPath()) {
            $copiedImport->setCatalogImagesPath($cImagesPath);
        }

        return $copiedImport;
    }
}
