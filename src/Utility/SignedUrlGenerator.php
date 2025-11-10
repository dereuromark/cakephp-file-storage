<?php declare(strict_types=1);

namespace FileStorage\Utility;

use Cake\Core\Configure;
use Cake\Utility\Security;
use FileStorage\Model\Entity\FileStorage;

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
  * The signature includes the file ID, path, modification time, and expiration
  * to ensure it becomes invalid if the file changes or time expires.
  *
  * @param \FileStorage\Model\Entity\FileStorage $entity File storage entity
  * @param array<string, mixed> $options Options
  *   - expires: Unix timestamp when signature expires (optional)
  *   - secret: Custom secret key (defaults to configured secret or Security salt)
  *
  * @return array<string, int|string|null> Array with 'signature' and 'expires' keys
  */
    public static function generate(FileStorage $entity, array $options = []): array
    {
        $expires = $options['expires'] ?? null;
        $secret = $options['secret'] ??
                  Configure::read('FileStorage.signatureSecret') ??
                  Security::getSalt();

        // Include data that should invalidate signature if changed
        $data = implode('|', [
            $entity->id,
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
}
