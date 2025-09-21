<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The abstract CSV command. Used to work with CSV files
 */
abstract class AbstractCsvCommand extends Command
{
    protected const string OPT_FILE_ID = 'file-id';
    protected const string OPT_FILE_PATH = 'file-path';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption(
            self::OPT_FILE_PATH,
            'p',
            InputOption::VALUE_OPTIONAL,
            'CSV File Path'
        );
        $this->addOption(
            self::OPT_FILE_ID,
            'i',
            InputOption::VALUE_OPTIONAL,
            'File ID'
        );
        parent::configure();
    }

    /**
     * Resolves the file ID
     *
     * @param InputInterface $input
     * @return string|null
     */
    protected function resolveFileId(InputInterface $input): ?string
    {
        $fileId = $input->getOption(self::OPT_FILE_ID)
            ?: $input->getOption(self::OPT_FILE_PATH);

        return empty($fileId) ? null : (string)$fileId;
    }
}
