<?php
/**
 * @var \Cake\View\View $this
 * @var \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> $fileStorage
 * @var bool $queueLoaded
 */

use Cake\Core\Plugin;

$this->assign('title', __d('file_storage', 'Files'));
$cspNonce = (string)$this->getRequest()->getAttribute('cspNonce', '');
?>
<h1 class="h3 mb-3 d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file me-2 text-primary"></i><?= __d('file_storage', 'Files') ?></span>
</h1>

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
