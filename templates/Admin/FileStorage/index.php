<?php
/**
 * @var \Cake\View\View $this
 * @var \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> $fileStorage
 * @var array<int, string> $models
 * @var array<int, string> $collections
 * @var array{model: string, collection: string, mime: string, q: string, created_from: string, created_to: string, min_size: string, fk: string} $filterValues
 * @var bool $queueLoaded
 */

use Cake\Core\Plugin;

$this->assign('title', __d('file_storage', 'Files'));
$cspNonce = (string)$this->getRequest()->getAttribute('cspNonce', '');

$activeFilters = array_filter($filterValues, static fn (string $v): bool => $v !== '');
?>
<h1 class="h3 mb-3 d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file me-2 text-primary"></i><?= __d('file_storage', 'Files') ?></span>
</h1>

<form method="get" class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i><?= __d('file_storage', 'Filters') ?></span>
        <?php if ($activeFilters): ?>
            <span class="badge bg-primary"><?= count($activeFilters) ?> <?= __d('file_storage', 'active') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-2">
        <div class="row g-2">
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Model') ?></label>
                <select class="form-select form-select-sm" name="model">
                    <option value=""><?= __d('file_storage', '— all —') ?></option>
                    <?php foreach ($models as $modelOption): ?>
                        <option value="<?= h($modelOption) ?>" <?= $filterValues['model'] === $modelOption ? 'selected' : '' ?>><?= h($modelOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Collection') ?></label>
                <select class="form-select form-select-sm" name="collection">
                    <option value=""><?= __d('file_storage', '— all —') ?></option>
                    <?php foreach ($collections as $collectionOption): ?>
                        <option value="<?= h($collectionOption) ?>" <?= $filterValues['collection'] === $collectionOption ? 'selected' : '' ?>><?= h($collectionOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Mime prefix') ?></label>
                <input type="text" class="form-control form-control-sm" name="mime"
                    value="<?= h($filterValues['mime']) ?>"
                    list="fs-mime-suggestions"
                    placeholder="image/">
                <datalist id="fs-mime-suggestions">
                    <option value="image/">
                    <option value="application/pdf">
                    <option value="video/">
                    <option value="audio/">
                    <option value="text/">
                </datalist>
            </div>
            <div class="col-md-3 col-lg-3">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Filename contains') ?></label>
                <input type="text" class="form-control form-control-sm" name="q"
                    value="<?= h($filterValues['q']) ?>"
                    placeholder="<?= h(__d('file_storage', 'substring match')) ?>">
            </div>
            <div class="col-md-3 col-lg-1">
                <label class="form-label small mb-1"><?= __d('file_storage', 'FK') ?></label>
                <input type="number" class="form-control form-control-sm" name="fk"
                    value="<?= h($filterValues['fk']) ?>"
                    min="0" step="1"
                    placeholder="<?= h(__d('file_storage', 'id')) ?>">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Min size (MB)') ?></label>
                <input type="number" class="form-control form-control-sm" name="min_size"
                    value="<?= h($filterValues['min_size']) ?>"
                    min="0" step="0.1"
                    placeholder="0">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Created from') ?></label>
                <input type="date" class="form-control form-control-sm" name="created_from"
                    value="<?= h($filterValues['created_from']) ?>">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1"><?= __d('file_storage', 'Created to') ?></label>
                <input type="date" class="form-control form-control-sm" name="created_to"
                    value="<?= h($filterValues['created_to']) ?>">
            </div>
            <div class="col-md-12 col-lg d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search me-1"></i><?= __d('file_storage', 'Filter') ?>
                </button>
                <?php if ($activeFilters): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i><?= __d('file_storage', 'Reset') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?= $this->Form->create(null, [
    'url' => ['action' => 'deleteBulk'],
    'class' => 'mb-0',
    'data-confirm-message' => __d('file_storage', 'Delete the selected file storage entries? This cannot be undone.'),
]) ?>
<div class="card mb-3">
    <div class="card-body p-2 d-flex gap-2 align-items-center flex-wrap">
        <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-trash me-1"></i><?= __d('file_storage', 'Delete selected') ?>
        </button>
        <span class="text-muted small ms-auto">
            <?= __d('file_storage', 'Tip: tick the header checkbox to select all rows on this page.') ?>
        </span>
    </div>
</div>

<div class="fs-table table-responsive">
    <table class="table table-sm table-striped mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width:40px;">
                    <input type="checkbox" class="form-check-input" data-fs-checkall="#fs-files-table" aria-label="<?= h(__d('file_storage', 'Select all')) ?>">
                </th>
                <th><?= $this->Paginator->sort('model') ?></th>
                <th><?= $this->Paginator->sort('collection') ?></th>
                <th><?= $this->Paginator->sort('filename') ?></th>
                <th><?= $this->Paginator->sort('filesize') ?></th>
                <th><?= $this->Paginator->sort('mime_type') ?></th>
                <th><?= $this->Paginator->sort('created', null, ['direction' => 'desc']) ?></th>
                <th class="text-end"><?= __d('file_storage', 'Actions') ?></th>
            </tr>
        </thead>
        <tbody id="fs-files-table">
            <?php foreach ($fileStorage as $entry): ?>
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" name="ids[]" value="<?= h($entry->id) ?>" aria-label="<?= h(__d('file_storage', 'Select row')) ?>">
                    </td>
                    <td><?= h($entry->model) ?>:<?= h((string)$entry->foreign_key) ?></td>
                    <td><?= h($entry->collection) ?></td>
                    <td>
                        <?= h($entry->filename) ?>
                        <div><small class="text-muted"><?= h($entry->path) ?></small></div>
                    </td>
                    <td><?= $this->Number->toReadableSize($entry->filesize) ?></td>
                    <td>
                        <?= h($entry->mime_type) ?>
                        <div><small class="text-muted">Ext: <?= h($entry->extension) ?></small></div>
                    </td>
                    <td><?= h($entry->created?->format('Y-m-d H:i') ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'view', $entry->id]) ?>" title="<?= h(__d('file_storage', 'View')) ?>">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'download', $entry->id]) ?>" title="<?= h(__d('file_storage', 'Download')) ?>">
                            <i class="fas fa-download"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'edit', $entry->id]) ?>" title="<?= h(__d('file_storage', 'Edit')) ?>">
                            <i class="fas fa-pen"></i>
                        </a>
                        <?php if ($queueLoaded): ?>
                            <?= $this->Form->postButton(
                                '<i class="fas fa-sync"></i>',
                                ['action' => 'regenerateVariants', $entry->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-info',
                                    'escapeTitle' => false,
                                    'title' => __d('file_storage', 'Queue variant regeneration'),
                                    'form' => [
                                        'class' => 'd-inline',
                                        'data-confirm-message' => __d('file_storage', 'Queue a variant regeneration job for {0}?', $entry->filename),
                                    ],
                                ],
                            ) ?>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                data-bs-toggle="tooltip"
                                title="<?= h(__d('file_storage', 'Install dereuromark/cakephp-queue to enable background variant regeneration.')) ?>">
                                <i class="fas fa-sync"></i>
                            </button>
                        <?php endif; ?>
                        <?= $this->Form->postButton(
                            Plugin::isLoaded('Tools') ? $this->Icon->render('delete') : '<i class="fas fa-trash"></i>',
                            ['action' => 'delete', $entry->id],
                            [
                                'class' => 'btn btn-sm btn-outline-danger',
                                'escapeTitle' => false,
                                'title' => __d('file_storage', 'Delete'),
                                'form' => [
                                    'class' => 'd-inline',
                                    'data-confirm-message' => __d('file_storage', 'Delete {0}?', $entry->filename),
                                ],
                            ],
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $this->Form->end() ?>

<?php if (Plugin::isLoaded('Tools')) {
    echo $this->element('Tools.pagination');
} else { ?>
    <nav class="mt-3">
        <ul class="pagination">
            <?= $this->Paginator->prev('« ' . __d('file_storage', 'previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__d('file_storage', 'next') . ' »') ?>
        </ul>
        <p><?= $this->Paginator->counter() ?></p>
    </nav>
<?php } ?>
