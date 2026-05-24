<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use App\Controller\AppController;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Log\Log;
use Closure;
use Throwable;

/**
 * Base controller for the FileStorage admin backend.
 *
 * Hosts the fail-closed authorization gate (`FileStorage.adminAccess`) and the
 * standalone-mode / layout switches that make this plugin's admin UI
 * mountable both inside the host's existing admin shell and as a fully
 * self-contained backend (Bootstrap 5 + Font Awesome 6 via CDN).
 *
 * Configuration keys (all under the `FileStorage.` namespace):
 *
 * - `adminAccess` (default: unset → 403)
 *     Authorization gate. See {@see self::beforeFilter()}.
 * - `standalone` (default: false)
 *     When true, skips the host application's `App\Controller\AppController`
 *     `initialize()` chain and runs against `Cake\Controller\Controller` only.
 *     Useful for projects without their own admin shell.
 * - `adminLayout` (default: null)
 *     - `null` → use the bundled `FileStorage.file_storage` layout.
 *     - `false` → fall back to the host application's default layout
 *                  (the pre-4.3 behaviour).
 *     - `string` → use the given layout (`FileStorage.file_storage` or any custom).
 */
class FileStorageAppController extends AppController
{
    use LoadHelperTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        if (Configure::read('FileStorage.standalone') === true) {
            // Standalone mode: skip the host's AppController chain entirely.
            Controller::initialize();
            $this->loadComponent('Flash');
        } else {
            parent::initialize();
        }

        $this->loadFileStorageHelpers();

        $layout = Configure::read('FileStorage.adminLayout');
        if ($layout !== false) {
            $this->viewBuilder()->setLayout(is_string($layout) ? $layout : 'FileStorage.file_storage');
        }
    }

    /**
     * Fail-closed authorization gate for the admin actions.
     *
     * Reads `Configure::read('FileStorage.adminAccess')`:
     *
     * - `null` / unset (default) → `ForbiddenException`. Consumers MUST opt in.
     * - `true` → allow. Use when an upstream gate (Authentication+Authorization,
     *   TinyAuth, custom middleware) already protects the `Admin` prefix.
     * - `Closure(\Cake\Http\ServerRequest): bool` → allow only when the closure
     *   returns literal `true`.
     *
     * Anything else (false, string, int, …) is treated as deny. Any exception
     * thrown from the closure is logged and converted to a generic 403 — the
     * gate must not leak internal error detail to unauthenticated callers.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event
     *
     * @throws \Cake\Http\Exception\ForbiddenException When access is not explicitly granted.
     *
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Coexist with cakephp/authorization: this gate IS the authorization
        // decision for the admin controllers, so silence the policy check.
        if (
            $this->components()->has('Authorization')
            && method_exists($this->components()->get('Authorization'), 'skipAuthorization')
        ) {
            $this->components()->get('Authorization')->skipAuthorization();
        }

        $access = Configure::read('FileStorage.adminAccess');
        if ($access === true) {
            return;
        }

        if ($access instanceof Closure) {
            try {
                $allowed = $access($this->request) === true;
            } catch (Throwable $e) {
                Log::warning(sprintf(
                    'FileStorage.adminAccess threw %s: %s',
                    $e::class,
                    $e->getMessage(),
                ));

                throw new ForbiddenException('FileStorage admin access denied.');
            }

            if ($allowed) {
                return;
            }

            throw new ForbiddenException('FileStorage admin access denied.');
        }

        throw new ForbiddenException(
            'Admin access to FileStorage is not configured. Set `FileStorage.adminAccess` to `true` '
            . '(when an upstream auth gate already protects the `Admin` prefix) '
            . 'or to a `Closure(\Cake\Http\ServerRequest): bool` to authorize per-request.',
        );
    }
}
