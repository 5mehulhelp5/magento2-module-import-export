<?php

declare(strict_types=1);

namespace EPuzzle\ImportExport\Model;

use EPuzzle\ImportExport\Api\Data\ImportResultInterface;
use Magento\Framework\DataObject;

/**
 * The import result entity
 */
class ImportResult extends DataObject implements ImportResultInterface
{
    /**
     * @inheritDoc
     */
    public function getFileId(): int
    {
        return (int)$this->getData('file_id');
    }

    /**
     * @inheritDoc
     */
    public function setFileId(int $value): void
    {
        $this->setData('file_id', $value);
    }

    /**
     * @inheritDoc
     */
    public function getIsSuccess(): bool
    {
        return (bool)$this->getData('is_success');
    }

    /**
     * @inheritDoc
     */
    public function setIsSuccess(bool $value): void
    {
        $this->setData('is_success', $value);
    }

    /**
     * @inheritDoc
     */
    public function getMessages(): array
    {
        $value = $this->getData('messages');

        return is_array($value) ? $value : [];
    }

    /**
     * @inheritDoc
     */
    public function setMessages(array $value): void
    {
        $this->setData('messages', $value);
    }

    /**
     * @inheritDoc
     */
    public function addMessage(string $value): void
    {
        $messages = $this->getMessages();
        $messages[] = $value;
        $this->setMessages($messages);
    }

    /**
     * @inheritDoc
     */
    public function getLogTrace(): string
    {
        return (string)$this->getData('log_trace');
    }

    /**
     * @inheritDoc
     */
    public function setLogTrace(string $value): void
    {
        $this->setData('log_trace', $value);
    }

    /**
     * @inheritDoc
     */
    public function getErrorReportUrl(): ?string
    {
        return $this->getData('error_report_download_url');
    }

    /**
     * @inheritDoc
     */
    public function setErrorReportUrl(?string $value): void
    {
        $this->setData('error_report_download_url', $value);
    }
}
