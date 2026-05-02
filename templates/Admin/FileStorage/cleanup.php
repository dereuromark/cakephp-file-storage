<?php
/**
 * @var \Cake\View\View $this
 * @var array<int, string> $models
 * @var \FileStorage\Service\CleanupReport|null $report
 * @var string|null $previewModel
 * @var string|null $previewCollection
 */

use FileStorage\Service\CleanupReport;

$this->assign('title', __d('file_storage', 'Cleanup'));
?>
<h1 class="h3 mb-4"><i class="fas fa-broom me-2 text-primary"></i><?= __d('file_storage', 'Storage Cleanup') ?></h1>

<div class="card mb-4">
    <div class="card-header"><?= __d('file_storage', 'Scope') ?></div>
    <div class="card-body">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-3 align-items-end']) ?>
        <div class="col-md-4">
            <label class="form-label"><?= __d('file_storage', 'Model') ?></label>
            <?= $this->Form->control('model', [
                'type' => 'select',
                'options' => array_combine($models, $models),
                'empty' => __d('file_storage', '— all —'),
                'value' => $previewModel,
                'label' => false,
                'class' => 'form-select',
            ]) ?>
        </div>
        <div class="col-md-4">
            <label class="form-label"><?= __d('file_storage', 'Collection') ?></label>
            <?= $this->Form->control('collection', [
                'type' => 'text',
                'value' => $previewCollection,
                'label' => false,
                'class' => 'form-control',
                'placeholder' => __d('file_storage', '(leave empty for all)'),
            ]) ?>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search me-1"></i><?= __d('file_storage', 'Preview (dry-run)') ?>
            </button>
        </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<?php if ($report instanceof CleanupReport): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-list me-2"></i><?= __d('file_storage', 'Dry-run report') ?></span>
            <small class="text-muted"><?= __d('file_storage', 'Checked {0} rows', number_format($report->checkedCount)) ?></small>
        </div>
        <div class="card-body">
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-unlink me-2 text-warning"></i><?= __d('file_storage', 'Orphan rows (would delete)') ?></span>
                    <span class="badge bg-warning text-dark"><?= number_format($report->deletedRows) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-trash me-2 text-danger"></i><?= __d('file_storage', 'Orphan files on disk (would delete)') ?></span>
                    <span class="badge bg-danger"><?= number_format(count($report->deletedFiles)) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-question-circle me-2 text-info"></i><?= __d('file_storage', 'Rows with missing backing files') ?></span>
                    <span class="badge bg-info text-dark"><?= number_format(count($report->missingFiles)) ?></span>
                </li>
            </ul>

            <?php if ($report->warnings): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($report->warnings as $warning): ?>
                            <li><?= h($warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($report->deletedFiles): ?>
                <details class="mb-3">
                    <summary><?= __d('file_storage', 'Show {0} orphan file paths', count($report->deletedFiles)) ?></summary>
                    <pre class="small mb-0"><?php foreach ($report->deletedFiles as $path) {
                        echo h($path) . "\n";
                    } ?></pre>
                </details>
            <?php endif; ?>

            <?php if ($report->missingFiles): ?>
                <details class="mb-3">
                    <summary><?= __d('file_storage', 'Show {0} rows with missing files', count($report->missingFiles)) ?></summary>
                    <table class="table table-sm">
                        <thead><tr><th><?= __d('file_storage', 'Row id') ?></th><th><?= __d('file_storage', 'Missing variants') ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($report->missingFiles as $row): ?>
                                <tr>
                                    <td><?= h($row['id']) ?></td>
                                    <td><?= h(implode(', ', $row['missing'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

            <?php if ($report->deletedRows > 0 || $report->deletedFiles): ?>
                <?= $this->Form->postButton(
                    '<i class="fas fa-trash me-1"></i>' . __d('file_storage', 'Run cleanup now'),
                    ['action' => 'cleanup'],
                    [
                        'data' => ['model' => $previewModel, 'collection' => $previewCollection],
                        'class' => 'btn btn-danger',
                        'escapeTitle' => false,
                        'form' => [
                            'data-confirm-message' => __d('file_storage', 'Sure? This permanently deletes the orphan rows and files listed above.'),
                        ],
                    ],
                ) ?>
            <?php else: ?>
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i><?= __d('file_storage', 'Nothing to clean up — storage is in sync.') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
