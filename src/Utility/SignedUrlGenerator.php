<?php declare(strict_types=1);

namespace FileStorage\Utility;

use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\Utility\Security;
use FileStorage\Model\Entity\FileStorage;
use InvalidArgumentException;

/**
 * Generate and verify signed URLs for temporary file access
 *
 * Signed URLs allow time-limited access to files without authentication.
 * Useful for share links, email attachments, and API integrations.
 *
 * @link https://github.com/dereuromark/cakephp-file-storage
 */
class SignedUrlGenerator
{
 /**
  * Generate signature data for a file
  *
  * The signature includes the file UUID, path, modification time, and expiration
  * to ensure it becomes invalid if the file changes or time expires.
  *
  * @param \FileStorage\Model\Entity\FileStorage $entity File storage entity
  * @param array<string, mixed> $options Options
  *   - expires: Unix timestamp when signature expires (optional)
  *   - secret: Custom secret key (defaults to configured secret or Security salt)
  *
  * @throws \InvalidArgumentException
  *
  * @return array<string, int|string|null> Array with 'signature' and 'expires' keys
  */
    public static function generate(FileStorage $entity, array $options = []): array
    {
        if (!$entity->publicId()) {
            throw new InvalidArgumentException('Cannot sign a file storage entity without a UUID.');
        }
        if (!$entity->path) {
            throw new InvalidArgumentException('Cannot sign a file storage entity without a path.');
        }

        $expires = $options['expires'] ?? null;
        $secret = $options['secret'] ??
                  Configure::read('FileStorage.signatureSecret') ??
                  Security::getSalt();

        // Include data that should invalidate signature if changed
        $data = implode('|', [
            $entity->publicId(),
            $entity->path,
            $entity->modified->toUnixString(),
            $expires ?? '',
        ]);

        $signature = hash_hmac('sha256', $data, $secret);

        return [
            'signature' => $signature,
            'expires' => $expires,
        ];
    }

    /**
     * Verify a signature is valid
     *
     * Checks both signature validity and expiration time using timing-safe comparison.
     *
     * @param \FileStorage\Model\Entity\FileStorage $entity File storage entity
     * @param string $signature Signature to verify
     * @param array<string, mixed> $options Options
     *   - expires: Unix timestamp signature should match (optional)
     *   - secret: Custom secret key (defaults to configured secret or Security salt)
     *
     * @return bool True if signature is valid and not expired
     */
    public static function verify(FileStorage $entity, string $signature, array $options = []): bool
    {
        $expires = $options['expires'] ?? null;

        // Check expiration first (cheaper operation)
        if ($expires !== null && (int)$expires < time()) {
            return false;
        }

        // Generate expected signature with same parameters
        $expected = static::generate($entity, $options);

        // Timing-safe comparison to prevent timing attacks
        return hash_equals((string)$expected['signature'], $signature);
    }

    /**
     * Build a fully-routable signed-download URL for this entity.
     *
     * Pairs with `FileStorage\Controller\FileStorageController::signed()`.
     * The returned URL embeds the signature in the path segment and the
     * expiration in the query string so reverse-proxy access logs scrub
     * the secret half (signature) less aggressively than they would
     * scrub a `?signature=...` query parameter — but the signature alone
     * is useless without the entity-derived data this class hashes into
     * `generate()`, so log exposure is bounded.
     *
     * Set `'fullBase' => true` for an absolute URL (the common case for
     * email links and external embeds); the default is a relative path
     * suitable for same-origin templates.
     *
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param array<string, mixed> $options Forwarded to `generate()`, plus:
     *     - `fullBase` (bool, default false): emit an absolute URL.
     *
     * @return string The signed URL.
     */
    public static function url(FileStorage $entity, array $options = []): string
    {
        $fullBase = (bool)($options['fullBase'] ?? false);
        unset($options['fullBase']);

        $signed = static::generate($entity, $options);
        $url = [
            'plugin' => 'FileStorage',
            'prefix' => false,
            'controller' => 'FileStorage',
            'action' => 'signed',
            $entity->publicId(),
            $signed['signature'],
        ];
        if ($signed['expires'] !== null) {
            $url['?'] = ['expires' => $signed['expires']];
        }

        return Router::url($url, $fullBase);
    }
}
