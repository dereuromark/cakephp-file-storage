<?php
declare(strict_types = 1);

namespace FileStorage\Controller\Admin;

use App\Controller\AppController;

/**
 * @property \FileStorage\Model\Table\FileStorageTable $FileStorage
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> paginate($object = null, array $settings = [])
 */
class FileStorageController extends AppController
{
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
        $fileStorage = $this->FileStorage->get($id, [
            'contain' => [],
        ]);

        $this->set(compact('fileStorage'));
    }

    /**
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     */
    public function edit($id = null)
    {
        $fileStorage = $this->FileStorage->get($id, [
            'contain' => [],
        ]);
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

        return $this->redirect($redirect ?: ['action' => 'index']);
    }
}
