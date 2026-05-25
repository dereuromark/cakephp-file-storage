<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use FileStorage\Service\CleanupService;

/**
 * @property \FileStorage\Model\Table\FileStorageTable $FileStorage
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> paginate($object = null, array $settings = [])
 */
class FileStorageController extends FileStorageAppController
{
    /**
     * @return \Cake\Http\Response|null Renders view
     */
    public function index(): ?Response
    {
        $query = $this->FileStorage->find();
        $filters = $this->buildIndexFilters();
        if ($filters !== []) {
            $query->where($filters);
        }

        $this->paginate = [
            'order' => ['created' => 'DESC'],
        ];
        $fileStorage = $this->paginate($query);

        // Distinct lookups for the filter dropdowns. Same pattern the cleanup
        // action uses — values are admin-supplied and rare to change, so we
        // accept the two extra COUNT-DISTINCT-shaped queries per page render.
        $models = $this->FileStorage->find()
            ->select(['model'])
            ->where(['model IS NOT' => null])
            ->groupBy(['model'])
            ->orderByAsc('model')
            ->all()
            ->extract('model')
            ->toArray();

        $collections = $this->FileStorage->find()
            ->select(['collection'])
            ->where(['collection IS NOT' => null])
            ->groupBy(['collection'])
            ->orderByAsc('collection')
            ->all()
            ->extract('collection')
            ->toArray();

        $this->set(['fileStorage' => $fileStorage, 'models' => $models, 'collections' => $collections]);
        $this->set('queueLoaded', Plugin::isLoaded('Queue'));
        $this->set('filterValues', $this->indexFilterValues());

        return null;
    }

    /**
     * Pull filter values from the query string with empty-skip semantics.
     *
     * Returns the *raw user input* (strings) so the index template can
     * re-populate the form. {@see self::buildIndexFilters()} consumes the
     * same source to produce ORM-ready WHERE conditions.
     *
     * @return array{model: string, collection: string, mime: string, q: string, created_from: string, created_to: string, min_size: string, fk: string}
     */
    protected function indexFilterValues(): array
    {
        return [
            'model' => trim((string)$this->request->getQuery('model', '')),
            'collection' => trim((string)$this->request->getQuery('collection', '')),
            'mime' => trim((string)$this->request->getQuery('mime', '')),
            'q' => trim((string)$this->request->getQuery('q', '')),
            'created_from' => trim((string)$this->request->getQuery('created_from', '')),
            'created_to' => trim((string)$this->request->getQuery('created_to', '')),
            'min_size' => trim((string)$this->request->getQuery('min_size', '')),
            'fk' => trim((string)$this->request->getQuery('fk', '')),
        ];
    }

    /**
     * Translate the raw filter inputs into ORM WHERE conditions.
     *
     * Empty values are dropped — we never emit a `WHERE created >= ''` style
     * query. Mime uses prefix-LIKE so `image/` matches all images while
     * keeping the index usable; filename match is substring (`q` like a
     * search box) and accepts the index hit since this list is admin-sized.
     *
     * @return array<string, mixed>
     */
    protected function buildIndexFilters(): array
    {
        $values = $this->indexFilterValues();
        $conditions = [];

        if ($values['model'] !== '') {
            $conditions['model'] = $values['model'];
        }
        if ($values['collection'] !== '') {
            $conditions['collection'] = $values['collection'];
        }
        if ($values['mime'] !== '') {
            $conditions['mime_type LIKE'] = $values['mime'] . '%';
        }
        if ($values['q'] !== '') {
            $conditions['filename LIKE'] = '%' . $values['q'] . '%';
        }
        if ($values['created_from'] !== '') {
            $conditions['created >='] = $values['created_from'];
        }
        if ($values['created_to'] !== '') {
            // Inclusive end-of-day so the date input matches user intent.
            $conditions['created <='] = $values['created_to'] . ' 23:59:59';
        }
        if ($values['min_size'] !== '' && is_numeric($values['min_size'])) {
            // UI emits MB as float; convert to bytes here.
            $conditions['filesize >='] = (int)round((float)$values['min_size'] * 1024 * 1024);
        }
        if ($values['fk'] !== '' && ctype_digit($values['fk'])) {
            $conditions['foreign_key'] = (int)$values['fk'];
        }

        return $conditions;
    }

    /**
     * View method
     *
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null Renders view
     */
    public function view(?string $id = null): ?Response
    {
        $fileStorage = $this->FileStorage->get($id);

        $this->set(compact('fileStorage'));
        $this->set('queueLoaded', Plugin::isLoaded('Queue'));

        return null;
    }

    /**
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     */
    public function edit(?string $id = null): ?Response
    {
        $fileStorage = $this->FileStorage->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $fileStorage = $this->FileStorage->patchEntity($fileStorage, $this->request->getData());
            if ($this->FileStorage->save($fileStorage)) {
                $this->Flash->success(__d('file_storage', 'The file storage has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('file_storage', 'The file storage could not be saved. Please, try again.'));
        }
        $this->set(compact('fileStorage'));

        return null;
    }

    /**
     * @param string|null $id File Storage id.
     *
     * @return \Cake\Http\Response|null Redirects to index.
     */
    public function delete(?string $id = null): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $fileStorage = $this->FileStorage->get($id);
        $redirect = $this->request->getQuery('redirect');
        if ($this->FileStorage->delete($fileStorage)) {
            $this->Flash->success(__d('file_storage', 'The file storage has been deleted.'));
        } else {
            $this->Flash->error(__d('file_storage', 'The file storage could not be deleted. Please, try again.'));
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
    public function deleteBulk(): ?Response
    {
        $this->request->allowMethod(['post']);

        /** @var array<int, string|int> $ids */
        $ids = (array)$this->request->getData('ids', []);
        $ids = array_values(array_filter($ids, static fn ($id): bool => (string)$id !== ''));

        if (!$ids) {
            $this->Flash->warning(__d('file_storage', 'No file storage entries selected.'));

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
            $this->Flash->success(__dn(
                'file_storage',
                '{0} file storage entry deleted.',
                '{0} file storage entries deleted.',
                $deleted,
                $deleted,
            ));
        } elseif ($deleted > 0 && $failed > 0) {
            $this->Flash->warning(__d('file_storage', '{0} deleted, {1} failed.', $deleted, $failed));
        } else {
            $this->Flash->error(__d('file_storage', 'Bulk delete failed.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Storage-tree cleanup UI on top of the `file_storage cleanup` CLI command.
     *
     * GET → renders the form (and a dry-run preview if `model` query is set).
     * POST → executes the cleanup against the resolved scope.
     *
     * @return \Cake\Http\Response|null
     */
    public function cleanup(): ?Response
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

            $this->Flash->success(__d(
                'file_storage',
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

        $this->set(['models' => $models, 'report' => $report, 'previewModel' => $previewModel, 'previewCollection' => $previewCollection]);

        return null;
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
    public function regenerateVariants(?string $id = null): ?Response
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

        $this->Flash->success(__d('file_storage', 'Variant regeneration job has been queued for {0}.', h($entity->filename)));

        return $this->redirect($this->referer(['action' => 'index']));
    }

    /**
     * Stream a stored file (or one of its variants) back to the admin caller.
     *
     * Adapter-agnostic: reads via the configured Flysystem adapter so it works
     * for Local, S3, etc. Defaults to attachment disposition; pass `?inline=1`
     * to render inline (used by the variants preview in `view`).
     *
     * Query params:
     *   - `variant` Variant name (e.g. `thumb`). Empty/missing → main file.
     *   - `inline` Truthy → `Content-Disposition: inline`. Otherwise `attachment`.
     *
     * @param string|null $id File Storage id.
     *
     * @throws \Cake\Http\Exception\NotFoundException When the row, path, or backing file is missing.
     * @throws \Cake\Http\Exception\InternalErrorException When no adapter is configured.
     *
     * @return \Cake\Http\Response
     */
    public function download(?string $id = null): Response
    {
        $entity = $this->FileStorage->get($id);
        $variant = (string)$this->request->getQuery('variant', '');
        $inline = (bool)$this->request->getQuery('inline');

        if (!$entity->adapter) {
            throw new NotFoundException(__d('file_storage', 'File is not stored on any adapter.'));
        }

        if ($variant !== '') {
            $path = $entity->getVariantPath($variant);
            $base = pathinfo((string)$entity->filename, PATHINFO_FILENAME);
            $ext = $entity->extension !== null ? '.' . $entity->extension : '';
            $downloadName = $base . '_' . $variant . $ext;
        } else {
            $path = $entity->path;
            $downloadName = (string)$entity->filename;
        }

        if (!$path) {
            throw new NotFoundException(__d('file_storage', 'No path stored for the requested file.'));
        }

        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            throw new InternalErrorException(__d('file_storage', 'FileStorage adapter not configured.'));
        }

        $adapter = $fileStorage->getStorage((string)$entity->adapter);
        if (!$adapter->fileExists($path)) {
            throw new NotFoundException(__d('file_storage', 'Backing file is missing on the adapter.'));
        }

        $mime = (string)$entity->mime_type !== '' ? (string)$entity->mime_type : 'application/octet-stream';
        $response = $this->response
            ->withType($mime)
            ->withStringBody($adapter->read($path));

        if ($inline) {
            return $response->withHeader(
                'Content-Disposition',
                'inline; filename="' . str_replace('"', '', $downloadName) . '"',
            );
        }

        return $response->withDownload($downloadName);
    }
}
