<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Console\Command;

use EPuzzle\ImportExport\Api\CsvManagementInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Used to split CSV files into batches
 */
class CsvToBatchesCommand extends AbstractCsvCommand
{
    public const string NAME = 'epuzzle:import-export:csv-to-batches';
    private const OPT_MAX_SIZE = 'batch-size';

    /**
     * CsvToBatchesCommand
     *
     * @param CsvManagementInterface $csvManagement
     * @param string|null $name
     */
    public function __construct(
        private readonly CsvManagementInterface $csvManagement,
        ?string $name = self::NAME
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Split CSV file into batches');
        $this->addOption(
            self::OPT_MAX_SIZE,
            's',
            InputOption::VALUE_OPTIONAL,
            'Max Size'
        );
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $files = $this->csvManagement->toBatches(
                $this->resolveFileId($input),
                (int)$input->getOption(self::OPT_MAX_SIZE) ?: null
            );
            $output->writeln('<info>CSV batching is successful</info>');
            foreach ($files as $file) {
                $output->writeln(sprintf('Batch file ID: <info> %d</info>', $file->getEntityId()));
                $output->writeln(sprintf('Batch file path: <info>%s</info>', $file->getFullPath()));
            }
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
