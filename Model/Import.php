<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use EPuzzle\ImportExport\Api\Data\ImportInterface;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\ImportExport\Model\Import as DefaultImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;

/**
 * Used to import data
 */
class Import extends DefaultImport implements ImportInterface
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
     * @inheritDoc
     */
    public function validateAndImportCsv(string $filePath): bool
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
        $source = $this->_getSourceAdapter($filePath);
        $this->validateSource($source);
        $this->createHistoryReport($filePath, $this->getEntity());
        $isSuccess = !$this->getErrorAggregator()->hasFatalExceptions() && $this->importSource();
        if ($isSuccess) {
            $this->invalidateIndex();
        }

        return $isSuccess;
    }

    /**
     * @inheritDoc
     */
    public function getEntity(): string
    {
        return (string)$this->getData('entity');
    }

    /**
     * @inheritDoc
     */
    public function setEntity(string $value): void
    {
        $this->setData('entity', $value);
    }

    /**
     * @inheritDoc
     */
    public function getBehavior(): string
    {
        return (string)$this->getData('behavior');
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getCatalogImagesPath(): ?string
    {
        return $this->getData(self::FIELD_NAME_IMG_FILE_DIR);
    }

    /**
     * @inheritDoc
     */
    public function setCatalogImagesPath(?string $value): void
    {
        $this->setData(self::FIELD_NAME_IMG_FILE_DIR, $value);
    }
}
