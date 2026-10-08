<?php declare(strict_types=1);

namespace FileStorage\Controller;

use App\Controller\AppController;
use FileStorage\Http\TusServer;
use Psr\Http\Message\ResponseInterface;

/**
 * Application-routed resumable upload endpoint.
 *
 * @author Mark Scherer
 * @license MIT
 */
class UploadsController extends AppController
{
    public function collection(): ResponseInterface
    {
        return (new TusServer())->handle($this->getRequest());
    }

    public function resource(string $id): ResponseInterface
    {
        return (new TusServer())->handle($this->getRequest(), $id);
    }
}
