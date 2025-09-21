<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Api\Data;

/**
 * The import result entity
 */
interface ImportResultInterface
{
    /**
     * Gets the file ID
     *
     * @return int
     */
    public function getFileId(): int;

    /**
     * Sets the file ID
     *
     * @param int $value
     * @return void
     */
    public function setFileId(int $value): void;

    /**
     * Gets the success status
     *
     * @return bool
     */
    public function getIsSuccess(): bool;

    /**
     * Sets the success status
     *
     * @param bool $value
     * @return void
     */
    public function setIsSuccess(bool $value): void;

    /**
     * Gets the messages
     *
     * @return array
     */
    public function getMessages(): array;

    /**
     * Sets the messages
     *
     * @param array $value
     * @return void
     */
    public function setMessages(array $value): void;

    /**
     * Adds the message
     *
     * @param string $value
     * @return void
     */
    public function addMessage(string $value): void;

    /**
     * Gets the log trace
     *
     * @return string
     */
    public function getLogTrace(): string;

    /**
     * Sets the log trace
     *
     * @param string $value
     * @return void
     */
    public function setLogTrace(string $value): void;

    /**
     * Gets the error report URL
     *
     * @return string|null
     */
    public function getErrorReportUrl(): ?string;

    /**
     * Sets the error report URL
     *
     * @param string|null $value
     * @return void
     */
    public function setErrorReportUrl(?string $value): void;
}
