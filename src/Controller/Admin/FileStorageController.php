<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use Cake\Core\Plugin;
use Cake\Http\Exception\BadRequestException;
use FileStorage\Service\CleanupService;

/**
 * @property \FileStorage\Model\Table\FileStorageTable $FileStorage
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> paginate($object = null, array $settings = [])
 */
class FileStorageController extends FileStorageAppController
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
        $this->set('queueLoaded', Plugin::isLoaded('Queue'));
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
        $this->set('queueLoaded', Plugin::isLoaded('Queue'));
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

    /**
     * Bulk delete selected files. Reads an `ids[]` array from POST.
     *
     * @return \Cake\Http\Response|null
     */
    public function deleteBulk()
    {
        $this->request->allowMethod(['post']);

        /** @var array<int, string|int> $ids */
        $ids = (array)$this->request->getData('ids', []);
        $ids = array_values(array_filter($ids, static fn ($id): bool => (string)$id !== ''));

        if (!$ids) {
            $this->Flash->warning(__('No file storage entries selected.'));

            return $this->redirect(['action' => 'index']);
        }

        $deleted = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $entity = $this->FileStorage->find()->where(['id' => $id])->first();
            if ($entity === null) {
                $failed++;

                continue;
            }
            if ($this->FileStorage->delete($entity)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted > 0 && $failed === 0) {
            $this->Flash->success(__n(
                '{0} file storage entry deleted.',
                '{0} file storage entries deleted.',
                $deleted,
                $deleted,
            ));
        } elseif ($deleted > 0 && $failed > 0) {
            $this->Flash->warning(__('{0} deleted, {1} failed.', $deleted, $failed));
        } else {
            $this->Flash->error(__('Bulk delete failed.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Storage-tree cleanup UI on top of the `file_storage cleanup` CLI command.
     *
     * GET → renders the form (and a dry-run preview if `model` query is set).
     * POST → executes the cleanup against the resolved scope.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function cleanup()
    {
        $service = new CleanupService();

        $models = $this->FileStorage
            ->find()
            ->select(['model'])
            ->where(['model IS NOT' => null])
            ->groupBy(['model'])
            ->orderByAsc('model')
            ->all()
            ->extract('model')
            ->toArray();

        if ($this->request->is('post')) {
            $model = (string)$this->request->getData('model') ?: null;
            $collection = (string)$this->request->getData('collection') ?: null;
            $report = $service->run($model, $collection, dryRun: false);

            $this->Flash->success(__(
                'Cleanup complete: {0} orphaned files deleted, {1} orphaned rows removed.',
                count($report->deletedFiles),
                $report->deletedRows,
            ));

            return $this->redirect(['action' => 'cleanup']);
        }

        $previewModel = (string)$this->request->getQuery('model') ?: null;
        $previewCollection = (string)$this->request->getQuery('collection') ?: null;
        $report = null;
        if ($previewModel !== null || $previewCollection !== null) {
            $report = $service->run($previewModel, $previewCollection, dryRun: true);
        }

        $this->set(compact('models', 'report', 'previewModel', 'previewCollection'));
    }

    /**
     * Enqueue an image-variant regeneration job. Requires the cakephp-queue
     * plugin to be loaded — the action returns a 400 otherwise so the UI's
     * disabled state stays in sync with the runtime check.
     *
     * @param string|null $id File Storage id.
     *
     * @throws \Cake\Http\Exception\BadRequestException When the Queue plugin is unavailable.
     *
     * @return \Cake\Http\Response|null
     */
    public function regenerateVariants($id = null)
    {
        $this->request->allowMethod(['post']);

        if (!Plugin::isLoaded('Queue')) {
            throw new BadRequestException(
                'Variant regeneration requires the cakephp-queue plugin. '
                . 'Install dereuromark/cakephp-queue to enable this action.',
            );
        }

        $entity = $this->FileStorage->get($id);

        // Use Queue's bundled `Execute` task: enqueue a CLI invocation of the
        // existing `file_storage generate_image_variant` command. This avoids
        // shipping a custom queue task class (which would couple the plugin
        // to cakephp-queue at compile time) while still doing the work
        // out-of-band on a worker.
        /** @var \Queue\Model\Table\QueuedJobsTable $queuedJobs */
        $queuedJobs = $this->fetchTable('Queue.QueuedJobs');
        $queuedJobs->createJob(
            'Queue.Execute',
            [
                'command' => sprintf(
                    'bin/cake file_storage generate_image_variant %s %s',
                    escapeshellarg((string)$entity->model),
                    escapeshellarg((string)$entity->collection),
                ),
            ],
        );

        $this->Flash->success(__('Variant regeneration job has been queued for {0}.', h($entity->filename)));

        return $this->redirect($this->referer(['action' => 'index']));
    }
}
