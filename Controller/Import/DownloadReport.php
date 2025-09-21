<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Controller\Import;

use EPuzzle\ImportExport\Model\ConfigProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\RuntimeException;
use Magento\ImportExport\Helper\Report;
use Magento\ImportExport\Model\Import;

/**
 * Used to download import history files
 */
class DownloadReport implements HttpGetActionInterface
{
    /**
     * DownloadReport
     *
     * @param RequestInterface $request
     * @param FileFactory $resultFileFactory
     * @param Report $reportHelper
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly FileFactory $resultFileFactory,
        private readonly Report $reportHelper,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Download import history file
     *
     * @return ResponseInterface
     * @throws FileSystemException
     * @throws NotFoundException
     * @throws RuntimeException
     */
    public function execute(): ResponseInterface
    {
        $hash = (string)$this->request->getParam('hash');
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $fileName = basename((string)$this->request->getParam('fileName'));
        if ($hash !== $this->configProvider->securityHash()
            || empty($fileName)
            || !$this->reportHelper->importFileExists($fileName)) {
            throw new NotFoundException(__('File not found.'));
        }

        return $this->resultFileFactory->create(
            $fileName,
            ['type' => 'filename', 'value' => Import::IMPORT_HISTORY_DIR . $fileName],
            DirectoryList::VAR_IMPORT_EXPORT,
            'application/octet-stream',
            $this->reportHelper->getReportSize($fileName)
        );
    }
}
