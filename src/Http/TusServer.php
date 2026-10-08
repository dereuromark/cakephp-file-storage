<?php declare(strict_types=1);

namespace FileStorage\Http;

use Cake\Http\Response;
use Cake\Log\Log;
use FileStorage\Exception\UploadException;
use FileStorage\Exception\UploadInvalidException;
use FileStorage\Service\ResumableUploads;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * tus 1.0.0 creation, expiration, and termination endpoint.
 *
 * @author Mark Scherer
 * @license MIT
 */
class TusServer
{
    protected ResumableUploads $uploads;

    public function __construct(?ResumableUploads $uploads = null)
    {
        $this->uploads = $uploads ?? new ResumableUploads();
    }

    public function handle(ServerRequestInterface $request, ?string $id = null): ResponseInterface
    {
        $response = (new Response())->withHeader('Tus-Resumable', '1.0.0');
        $method = strtoupper($request->getMethod());
        if ($method === 'HEAD') {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }
        if ($method === 'OPTIONS') {
            $response = $response->withStatus(204)->withHeader('Tus-Version', '1.0.0')->withHeader('Tus-Extension', 'creation,expiration,termination');
            if ($this->uploads->limit('maxSize')) {
                $response = $response->withHeader('Tus-Max-Size', (string)$this->uploads->limit('maxSize'));
            }

            return $response;
        }
        if ($request->getHeaderLine('Tus-Resumable') !== '1.0.0') {
            return $response->withStatus(412)->withHeader('Tus-Version', '1.0.0');
        }
        $context = ['request' => $request];
        $row = null;
        try {
            if ($id === null && $method === 'POST') {
                $row = $this->uploads->create(self::number($request->getHeaderLine('Upload-Length')), $request->hasHeader('Upload-Metadata') ? $request->getHeaderLine('Upload-Metadata') : null, $context);
                // Relative, so a TLS-terminating proxy cannot turn it into an http URL.
                $location = (string)$request->getAttribute('base') . rtrim($request->getUri()->getPath(), '/') . '/' . $row['id'];
                $response = $response->withStatus(201)->withHeader('Location', $location);
            } elseif ($id !== null && in_array($method, ['HEAD', 'PATCH', 'DELETE'], true)) {
                $row = $this->uploads->offset($id, $context, match ($method) {
                    'PATCH' => 'write',
                    'DELETE' => 'delete',
                    default => 'read',
                });
                if ($method === 'HEAD') {
                    $response = $response->withStatus(200)->withHeader('Upload-Offset', (string)$row['upload_offset'])->withHeader('Upload-Length', (string)$row['size']);
                    if ($row['metadata'] !== null) {
                        $response = $response->withHeader('Upload-Metadata', $row['metadata']);
                    }
                } elseif ($method === 'PATCH') {
                    // Lookup and authorization precede header errors on session requests.
                    if ($request->getHeaderLine('Content-Type') !== 'application/offset+octet-stream') {
                        throw new UploadException('PATCH requires application/offset+octet-stream.', 415, $row);
                    }
                    $offset = self::number($request->getHeaderLine('Upload-Offset'));
                    $row = $this->uploads->append($id, $offset, $request->getBody(), $context);
                    $response = $response->withStatus(204)->withHeader('Upload-Offset', (string)$row['upload_offset']);
                } else {
                    $row = $this->uploads->terminate($id, $context);
                    $response = $response->withStatus(204);
                }
            } else {
                $response = $response->withStatus(405)->withHeader('Allow', $id === null ? 'OPTIONS, POST' : 'OPTIONS, HEAD, PATCH, DELETE');
            }
        } catch (UploadException $error) {
            if ($error->status >= 500) {
                Log::error('Resumable upload failed: ' . $error->getMessage());
            }
            $response = $response->withStatus($error->status);
            $row = $error->upload ?? $row;
        } catch (Throwable $error) {
            Log::error('Resumable upload failed: ' . $error::class . ': ' . $error->getMessage());
            $response = $response->withStatus(500);
        }
        if ($row !== null) {
            $response = $response->withHeader('Upload-Expires', $row['expires']->setTimezone('UTC')->format('D, d M Y H:i:s') . ' GMT');
        }

        return $response;
    }

    public static function number(string $value): int
    {
        $maximum = (string)PHP_INT_MAX;
        if (!preg_match('/^[0-9]{1,19}$/D', $value)) {
            throw new UploadInvalidException('Invalid upload number.');
        }
        $normalized = ltrim($value, '0');
        if (strlen($normalized) > strlen($maximum) || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            throw new UploadInvalidException('Upload number overflows.');
        }

        return (int)$value;
    }
}
