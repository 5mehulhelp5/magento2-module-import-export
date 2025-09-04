<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Console\Command;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use EPuzzle\FileUploader\Api\FileRepositoryInterface;
use EPuzzle\FileUploader\Api\FileUploaderManagementInterface;
use EPuzzle\ImportExport\Model\Import;
use Exception;
use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\ImportExport\Model\Import\RenderErrorMessages;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Used to import data using CSV file
 */
class CsvImportCommand extends Command
{
    public const string NAME = 'epuzzle:import-export:csv-import';

    /**
     * CsvImportCommand
     *
     * @param Import $import
     * @param DriverInterface $fileDriver
     * @param RenderErrorMessages $renderErrorMessages
     * @param EmulatedAdminhtmlAreaProcessor $emulatedAdminhtmlAreaProcessor
     * @param FileUploaderManagementInterface $fileUploaderManagement
     * @param FileRepositoryInterface $fileRepository
     * @param string $name
     */
    public function __construct(
        private readonly Import $import,
        private readonly DriverInterface $fileDriver,
        private readonly RenderErrorMessages $renderErrorMessages,
        private readonly EmulatedAdminhtmlAreaProcessor $emulatedAdminhtmlAreaProcessor,
        private readonly FileUploaderManagementInterface $fileUploaderManagement,
        private readonly FileRepositoryInterface $fileRepository,
        string $name = self::NAME,
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Import data using CSV file');
        $this->addOption(
            'file-path',
            'p',
            InputOption::VALUE_OPTIONAL,
            'CSV File Path'
        );
        $this->addOption(
            'file-id',
            'i',
            InputOption::VALUE_OPTIONAL,
            'File ID'
        );
        $this->addOption(
            'entity',
            'e',
            InputOption::VALUE_OPTIONAL,
            'Entity Type',
            'catalog_product'
        );
        $this->addOption(
            'output-format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Output format. Allowed formats: default, json.'
        );
        $this->addOption(
            'behavior',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Import behavior. Allowed values: append, add_update, replace, delete. Default: append.'
        );
        $this->addOption(
            'catalog-images-path',
            null,
            InputOption::VALUE_OPTIONAL,
            'Catalog images path.'
        );
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->emulatedAdminhtmlAreaProcessor->process(function () use ($input, $output) {
                if ($input->getOption('output-format') === 'json') {
                    return $this->doWithJsonOutput($input, $output);
                }

                return $this->doWithDefaultOutput($input, $output);
            });
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Do the action with default output
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function doWithDefaultOutput(InputInterface $input, OutputInterface $output): int
    {
        try {
            $isImported = $this->executeImport($input, $output);
            if ($isImported) {
                $output->writeln('Log trace:');
                $output->writeln($this->import->getFormatedLogTrace());
                $output->writeln('<info>The import was successful.</info>');
            } else {
                $this->renderErrorMessages->createErrorReport($this->import->getErrorAggregator());
                $output->writeln('<error>Import failed.</error>');
                foreach ($this->import->getErrorAggregator()->getAllErrors() as $error) {
                    $message = 'Row Number: ' . $error->getRowNumber()
                        . '; Error Code: ' . $error->getErrorCode()
                        . '; Error Message: ' . $error->getErrorMessage()
                        . ': Error Description: ' . $error->getErrorDescription();
                    $output->writeln('<error>' . $message . '</error>');
                }
            }
        } catch (Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Do the action with JSON output
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function doWithJsonOutput(InputInterface $input, OutputInterface $output): int
    {
        $outputData = [
            'success' => true,
            'messages' => []
        ];
        try {
            $isImported = $this->executeImport($input, $output);
            if ($isImported) {
                $outputData['messages'][] = 'The import was successful.';
            } else {
                $outputData['success'] = false;
                $this->renderErrorMessages->createErrorReport($this->import->getErrorAggregator());
                foreach ($this->import->getErrorAggregator()->getAllErrors() as $error) {
                    $message = 'Row Number: ' . $error->getRowNumber()
                        . '; Error Code: ' . $error->getErrorCode()
                        . '; Error Message: ' . $error->getErrorMessage()
                        . ': Error Description: ' . $error->getErrorDescription();
                    $outputData['messages'][] = $message;
                }
            }
            $output->write(json_encode($outputData));
        } catch (Exception $exception) {
            $outputData['success'] = false;
            $outputData['messages'][] = $exception->getMessage();
            $output->write(json_encode($outputData));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Execute the import
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     */
    private function executeImport(InputInterface $input, OutputInterface $output): bool
    {
        $csvFile = $this->resolveCsvFile($input);
        if ($csvFile->getType() !== 'text/csv') {
            throw new InvalidArgumentException(__('Provided file is not a CSV file.'));
        }
        $this->import->setEntity($input->getOption('entity') ?: 'catalog_product');
        $this->import->setBehavior($input->getOption('behavior') ?: Import::BEHAVIOR_APPEND);
        if ($cImagePath = $input->getOption('catalog-images-path')) {
            $this->import->setCatalogImagesPath($cImagePath);
        }

        return $this->import->validateAndImportCsv($csvFile)
            && !$this->import->getErrorAggregator()->hasToBeTerminated();
    }

    /**
     * Resolves the CSV file
     *
     * @param InputInterface $input
     * @return FileInterface
     * @throws LocalizedException
     * @throws FileSystemException
     * @throws NoSuchEntityException
     * @throws NotFoundException
     */
    private function resolveCsvFile(InputInterface $input): FileInterface
    {
        $fileId = (int)$input->getOption('file-id');
        if ($fileId) {
            return $this->fileRepository->get($fileId);
        }
        $filePath = $this->fileDriver->getRealPath((string)$input->getOption('file-path'));
        if (!$filePath) {
            throw new FileSystemException(__('Invalid CSV file path.'));
        }
        $fileUploaderSettings = $this->fileUploaderManagement->createFileUploaderSettings();
        $fileUploaderSettings->getExtensionAttributes()->setSystemFilePath($filePath);
        $files = $this->fileUploaderManagement->upload($fileUploaderSettings);

        return array_shift($files);
    }
}
