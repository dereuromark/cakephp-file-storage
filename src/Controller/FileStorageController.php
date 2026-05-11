<?php declare(strict_types=1);

namespace FileStorage\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Utility\SignedUrlGenerator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use ReflectionClass;

/**
 * Public-facing controller for signed-URL file downloads.
 *
 * Pairs with `SignedUrlGenerator` to consume time-limited share links
 * without authenticating the visitor: anyone holding a valid signature
 * can fetch the file until the embedded expiration passes (or the file's
 * `modified` timestamp changes, which invalidates the signature
 * implicitly per `SignedUrlGenerator::generate()`).
 *
 * Use case examples:
 *
 * - email a download link to a customer
 * - embed a video tag pointing at a non-public S3 bucket
 * - generate "magic links" to PDF invoices from API integrations
 *
 * Not for authenticated downloads — those go through the
 * `Admin\FileStorageController::download()` action which checks ACLs.
 */
class FileStorageController extends Controller
{
 /**
  * Authentication / authorization plugins typically register their components
  * via `AppController::initialize()`. We skip both here — the *whole point*
  * of a signed URL is to authorize anonymously via a presented signature.
  * Apps that load Authentication/Authorization globally should add this
  * action to their allow-list (typically by name in an `Allow` config).
  *
  * @return void
  */
    public function initialize(): void
    {
        parent::initialize();
        if (!$this->components()->has('Authentication')) {
            return;
        }
        $authentication = $this->components()->get('Authentication');
        if (method_exists($authentication, 'allowUnauthenticated')) {
            $authentication->allowUnauthenticated(['signed']);
        }
    }

    /**
     * Stream a file via a signed URL.
     *
     * URL: `/file-storage/signed/{id}/{signature}?expires={timestamp}`
     *
     * The signature must match what `SignedUrlGenerator::generate()` produced
     * for this entity with the same `expires` (if any). Mismatched signatures
     * 403; expired signatures 403; unknown ids 404; valid signatures whose
     * backing file disappeared on the adapter 404.
     *
     * For local-filesystem adapters the response uses `Response::withFile()`,
     * which gives HTTP `Range` support for free — important for `<video>` and
     * `<audio>` elements that send a Range header on first byte. For
     * non-local adapters (S3, etc.) we read the bytes once and emit them
     * with `Response::withStringBody()`; clients fall back to plain GET.
     *
     * @param string|int|null $id File storage row id.
     * @param string|null $signature URL-supplied signature.
     *
     * @throws \Cake\Http\Exception\BadRequestException
     * @throws \Cake\Http\Exception\ForbiddenException
     * @throws \Cake\Http\Exception\NotFoundException
     *
     * @return \Cake\Http\Response
     */
    public function signed($id = null, ?string $signature = null): Response
    {
        if ($id === null || $id === '' || $signature === null || $signature === '') {
            throw new BadRequestException('Missing id or signature.');
        }

        /** @var \FileStorage\Model\Table\FileStorageTable $table */
        $table = $this->fetchTable('FileStorage.FileStorage');

        /** @var \FileStorage\Model\Entity\FileStorage|null $entity */
        $entity = $table->find()->where(['FileStorage.id' => $id])->first();
        if ($entity === null) {
            throw new NotFoundException('File not found.');
        }

        $expires = $this->getRequest()->getQuery('expires');
        $options = [];
        if ($expires !== null) {
            $options['expires'] = (int)$expires;
        }

        if (!SignedUrlGenerator::verify($entity, $signature, $options)) {
            // Same status for "wrong signature" and "expired" so a probing
            // caller can't distinguish — the next-best outcome would be
            // 401, but that implies a way to authenticate, which signed
            // URLs don't have. 403 is the conventional choice.
            throw new ForbiddenException('Invalid or expired signature.');
        }

        return $this->streamEntity($entity);
    }

    /**
     * Resolve the storage adapter for this entity and stream its contents.
     *
     * @param \FileStorage\Model\Entity\FileStorage $entity
     *
     * @throws \Cake\Http\Exception\InternalErrorException
     * @throws \Cake\Http\Exception\NotFoundException
     *
     * @return \Cake\Http\Response
     */
    protected function streamEntity(FileStorage $entity): Response
    {
        $path = $entity->path;
        if (!$path) {
            throw new NotFoundException('No path stored for the requested file.');
        }

        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            throw new InternalErrorException('FileStorage adapter not configured.');
        }

        $adapter = $fileStorage->getStorage((string)$entity->adapter);
        if (!$adapter->fileExists($path)) {
            throw new NotFoundException('Backing file is missing on the adapter.');
        }

        $mime = (string)$entity->mime_type !== ''
            ? (string)$entity->mime_type
            : 'application/octet-stream';
        $filename = (string)$entity->filename;
        $disposition = 'inline; filename="' . str_replace('"', '', $filename) . '"';

        // Local adapter exposes a real filesystem path; use withFile() so we
        // get range-request handling, Last-Modified, and ETag essentially
        // for free. Non-local adapters (S3, FTP, custom) only expose a
        // `read()` API, so we materialize the bytes once into the response.
        $localPath = $this->resolveLocalPath($adapter, $path);
        if ($localPath !== null) {
            $response = $this->response
                ->withFile($localPath, ['name' => $filename])
                ->withType($mime)
                ->withHeader('Content-Disposition', $disposition);

            return $response;
        }

        return $this->response
            ->withType($mime)
            ->withHeader('Content-Disposition', $disposition)
            ->withStringBody($adapter->read($path));
    }

    /**
     * If the storage adapter is local, reach into it via its public
     * `prefixPath()` semantics to compute a real on-disk path. League's
     * `LocalFilesystemAdapter` doesn't expose that directly, but its
     * constructor stores the root and we can ask Flysystem to translate the
     * logical path via `prefixPath()` reflection — that's the supported
     * way to bridge to `withFile()`.
     *
     * Returns null for any non-local adapter so the caller falls back to
     * `read()` + string body.
     *
     * @param object $adapter Flysystem adapter instance.
     * @param string $relativePath
     *
     * @return string|null Absolute on-disk path, or null if the adapter
     *     isn't local-filesystem-backed.
     */
    protected function resolveLocalPath(object $adapter, string $relativePath): ?string
    {
        if (!$adapter instanceof LocalFilesystemAdapter) {
            return null;
        }

        // LocalFilesystemAdapter stores its prefixed pather as a private
        // property; the supported way to reach it is via reflection or by
        // keeping the root alongside the adapter at construction time.
        // The plugin's StorageService configures the adapter with a known
        // `root`, so we read that back via reflection — same approach the
        // CleanupService uses elsewhere in the plugin.
        $ref = new ReflectionClass($adapter);
        if (!$ref->hasProperty('prefixer')) {
            return null;
        }
        $prop = $ref->getProperty('prefixer');
        $prefixer = $prop->getValue($adapter);
        if (!is_object($prefixer) || !method_exists($prefixer, 'prefixPath')) {
            return null;
        }
        $absolute = $prefixer->prefixPath($relativePath);
        if (!is_string($absolute) || !is_file($absolute) || !is_readable($absolute)) {
            return null;
        }

        return $absolute;
    }
}
