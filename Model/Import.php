<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use EPuzzle\FileUploader\Api\Data\FileInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\ImportExport\Model\Import as DefaultImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;

/**
 * Used to import data
 */
class Import extends DefaultImport
{
    /**
     * @var string[]
     */
    private array $behaviourList = [
        DefaultImport::BEHAVIOR_APPEND,
        DefaultImport::BEHAVIOR_ADD_UPDATE,
        DefaultImport::BEHAVIOR_REPLACE,
        DefaultImport::BEHAVIOR_DELETE,
    ];

    /**
     * Validate and import CSV file
     *
     * @param FileInterface $csvFile
     * @return bool
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function validateAndImportCsv(FileInterface $csvFile): bool
    {
        $defaultData = [
            'entity' => 'catalog_product',
            'behavior' => self::BEHAVIOR_APPEND,
            self::FIELD_NAME_IMG_FILE_DIR => 'pub/media/catalog/product',
            self::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS,
            self::FIELD_FIELD_SEPARATOR => ',',
            self::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => ','
        ];
        foreach ($defaultData as $key => $defaultValue) {
            if (!$this->hasData($key)) {
                $this->setData($key, $defaultValue);
            }
        }
        $source = $this->_getSourceAdapter($csvFile->getFullPath());
        $this->validateSource($source);
        $this->createHistoryReport($csvFile->getFullPath(), $this->getEntity());
        $result = $this->importSource();
        if ($result) {
            $this->invalidateIndex();
        }

        return $result;
    }

    /**
     * Sets the path to the catalog images
     *
     * @param string $value
     * @return void
     */
    public function setCatalogImagesPath(string $value): void
    {
        $this->setData(self::FIELD_NAME_IMG_FILE_DIR, $value);
    }

    /**
     * Sets the import behavior
     *
     * @param string $value
     * @return void
     * @throws InvalidArgumentException
     */
    public function setBehavior(string $value): void
    {
        if (in_array($value, $this->behaviourList)) {
            $this->setData('behavior', $value);
        } else {
            throw new InvalidArgumentException(
                __('Invalid behavior. Allowed values: %1', implode(', ', $this->behaviourList))
            );
        }
    }

    /**
     * Sets the entity type
     *
     * @param string $value
     * @return void
     */
    public function setEntity(string $value): void
    {
        $this->setData('entity', $value);
    }
}
