<?php declare(strict_types=1);

namespace FileStorage\Exception;

use Cake\Datasource\EntityInterface;

/**
 * Upload operation failure.
 *
 * @author Mark Scherer
 * @license MIT
 */
class UploadLockedException extends UploadException
{
    /**
     * @param string $message
     * @param int $status
     * @param array<string, mixed>|null $upload
     * @param \Cake\Datasource\EntityInterface|null $entity
     */
    public function __construct(string $message, int $status = 423, ?array $upload = null, ?EntityInterface $entity = null)
    {
        parent::__construct($message, $status, $upload, $entity);
    }
}
