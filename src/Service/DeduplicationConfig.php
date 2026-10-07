<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;

/**
 * Looks up collection deduplication settings.
 *
 * @author Mark Scherer
 * @license MIT
 */
class DeduplicationConfig
{
    public static function isEnabled(?string $model, ?string $collection): bool
    {
        $collections = Configure::read('FileStorage.deduplicate.collections', false);
        if ($collections === true) {
            return true;
        }
        if (!is_array($collections) || $model === null) {
            return false;
        }
        $configured = $collections[$model] ?? false;
        if ($configured === true) {
            return true;
        }

        return is_array($configured) && $collection !== null && ($configured[$collection] ?? false) === true;
    }
}
