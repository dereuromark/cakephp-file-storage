<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Closure;

/**
 * @property \FileStorage\Model\Table\FileStorageTable $FileStorage
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> paginate($object = null, array $settings = [])
 */
class FileStorageController extends AppController
{
    /**
     * Fail-closed authorization gate for the admin actions.
     *
     * Reads `Configure::read('FileStorage.adminAccess')`:
     *
     * - `null` / unset (default) → `ForbiddenException`. Consumers MUST opt in.
     * - `true` → allow. Use when the host application already gates the `Admin` prefix
     *   (router scope middleware, Authentication+Authorization plugin, TinyAuth, etc.)
     *   and you trust that gate to keep unauthorized users out.
     * - `Closure(\Cake\Http\ServerRequest $request): bool` → allow when the closure
     *   returns true; deny otherwise. Use when you want to evaluate the current
     *   identity / role inline without standing up an Authorization stack.
     *
     * Anything else (false, string, int, …) is treated as deny.
     *
     * @param \Cake\Event\EventInterface $event
     *
     * @throws \Cake\Http\Exception\ForbiddenException When access is not explicitly granted.
     *
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $access = Configure::read('FileStorage.adminAccess');
        if ($access === true) {
            return;
        }

        if ($access instanceof Closure && $access($this->request) === true) {
            return;
        }

        throw new ForbiddenException(
            'Admin access to FileStorage is not configured. Set `FileStorage.adminAccess` to `true` '
            . '(when an upstream auth gate already protects the `Admin` prefix) '
            . 'or to a `Closure(\Cake\Http\ServerRequest): bool` to authorize per-request.',
        );
    }

    /**
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->paginate = [
            'order' => ['created' => 'DESC'],
        ];

        $fileStorage = $this->paginate($this->FileStorage);

        $this->set(compact('fileStorage'));
    }

    /**
     * View method
     *
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function view($id = null)
    {
        $fileStorage = $this->FileStorage->get($id);

        $this->set(compact('fileStorage'));
    }

    /**
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     */
    public function edit($id = null)
    {
        $fileStorage = $this->FileStorage->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $fileStorage = $this->FileStorage->patchEntity($fileStorage, $this->request->getData());
            if ($this->FileStorage->save($fileStorage)) {
                $this->Flash->success(__('The file storage has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The file storage could not be saved. Please, try again.'));
        }
        $this->set(compact('fileStorage'));
    }

    /**
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null|void Redirects to index.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $fileStorage = $this->FileStorage->get($id);
        $redirect = $this->request->getQuery('redirect');
        if ($this->FileStorage->delete($fileStorage)) {
            $this->Flash->success(__('The file storage has been deleted.'));
        } else {
            $this->Flash->error(__('The file storage could not be deleted. Please, try again.'));
        }

        if ($redirect === 'ref') {
            return $this->redirect($this->referer(['action' => 'index']));
        }

        if (is_string($redirect) && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//') && !str_starts_with($redirect, '/\\')) {
            return $this->redirect($redirect);
        }

        return $this->redirect(['action' => 'index']);
    }
}
