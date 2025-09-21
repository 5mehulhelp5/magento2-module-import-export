<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Api\Data;

/**
 * The import entity
 */
interface ImportInterface
{
    /**
     * Validate and import CSV file
     *
     * @param string $filePath
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function validateAndImportCsv(string $filePath): bool;

    /**
     * Gets the entity type
     *
     * @return string
     */
    public function getEntity(): string;

    /**
     * Sets the entity type
     *
     * @param string $value
     * @return void
     */
    public function setEntity(string $value): void;

    /**
     * Gets the import behavior
     *
     * @return string
     */
    public function getBehavior(): string;

    /**
     * Sets the import behavior
     *
     * @param string $value
     * @return void
     * @throws \Magento\Framework\Exception\InvalidArgumentException
     */
    public function setBehavior(string $value): void;

    /**
     * Gets the path to the catalog images
     *
     * @return string|null
     */
    public function getCatalogImagesPath(): ?string;

    /**
     * Sets the path to the catalog images
     *
     * @param string|null $value
     * @return void
     */
    public function setCatalogImagesPath(?string $value): void;
}
