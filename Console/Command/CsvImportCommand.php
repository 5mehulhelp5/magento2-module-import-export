<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Console\Command;

use EPuzzle\ImportExport\Api\CsvManagementInterface;
use EPuzzle\ImportExport\Api\Data\ImportInterface;
use EPuzzle\ImportExport\Model\Import;
use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Used to import data using CSV file
 */
class CsvImportCommand extends AbstractCsvCommand
{
    public const string NAME = 'epuzzle:import-export:csv-import';

    /**
     * CsvImportCommand
     *
     * @param EmulatedAdminhtmlAreaProcessor $emulatedAdminhtmlAreaProcessor
     * @param CsvManagementInterface $csvManagement
     * @param ImportInterface $import
     * @param string|null $name
     */
    public function __construct(
        private readonly EmulatedAdminhtmlAreaProcessor $emulatedAdminhtmlAreaProcessor,
        private readonly CsvManagementInterface $csvManagement,
        private readonly ImportInterface $import,
        ?string $name = self::NAME
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
            'entity',
            'e',
            InputOption::VALUE_OPTIONAL,
            'Entity Type',
            'catalog_product'
        );
        $this->addOption(
            'behavior',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Import behavior. Allowed values: append, add_update, replace, delete. Default: append'
        );
        $this->addOption(
            'catalog-images-path',
            null,
            InputOption::VALUE_OPTIONAL,
            'Catalog images path'
        );
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->emulatedAdminhtmlAreaProcessor->process(function () use ($input, $output) {
                $fileId = $this->resolveFileId($input);
                $this->import->setEntity($input->getOption('entity') ?: 'catalog_product');
                $this->import->setBehavior($input->getOption('behavior') ?: Import::BEHAVIOR_APPEND);
                $this->import->setCatalogImagesPath($input->getOption('catalog-images-path') ?: null);
                foreach ($this->csvManagement->import($this->import, $fileId) as $importResult) {
                    $output->writeln(
                        sprintf("\r\nThe file ID: <info>%d.</info> Log Trace:\r\n", $importResult->getFileId())
                    );
                    $output->writeln($importResult->getLogTrace());
                    $messageTemplate = $importResult->getIsSuccess() ? '<info>%s</info>' : '<error>%s</error>';
                    foreach ($importResult->getMessages() as $message) {
                        $output->writeln(sprintf($messageTemplate, $message));
                    }
                    $errorReportUrl = $importResult->getErrorReportUrl();
                    if ($errorReportUrl) {
                        $output->writeln("\r\nDownload <error>error</error> report: $errorReportUrl");
                    }
                }
            });
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln($exception->getTraceAsString());
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
