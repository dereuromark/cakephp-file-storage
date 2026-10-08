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
        $this->skipAppAuthorization();

        return (new TusServer())->handle($this->getRequest());
    }

    public function resource(string $id): ResponseInterface
    {
        $this->skipAppAuthorization();

        return (new TusServer())->handle($this->getRequest(), $id);
    }

    /**
     * The resumable authorizer decides access, so the Authorization plugin's required check is satisfied here.
     *
     * @return void
     */
    protected function skipAppAuthorization(): void
    {
        $service = $this->getRequest()->getAttribute('authorization');
        if (is_object($service) && method_exists($service, 'skipAuthorization')) {
            $service->skipAuthorization();
        }
    }
}
