<?php declare(strict_types=1);

namespace FileStorage\Exception;

use Cake\Datasource\EntityInterface;
use RuntimeException;

/**
 * Upload operation failure.
 *
 * @author Mark Scherer
 * @license MIT
 */
class UploadException extends RuntimeException
{
    /**
     * @param string $message
     * @param int $status
     * @param array<string, mixed>|null $upload
     * @param \Cake\Datasource\EntityInterface|null $entity
     */
    public function __construct(string $message, public readonly int $status = 500, public readonly ?array $upload = null, public readonly ?EntityInterface $entity = null)
    {
        parent::__construct($message);
    }
}
