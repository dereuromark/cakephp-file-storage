<?php
/**
 * @var \Cake\View\View $this
 * @var int $totalCount
 * @var int|float $totalBytes
 * @var int $orphanCount
 * @var array<int, array{collection: string, count: int, bytes: int|float}> $byCollection
 * @var array<int, array{model: string, count: int, bytes: int|float}> $byModel
 * @var array<int, array{adapter: string, count: int}> $byAdapter
 * @var array<int, array{mime_type: string, count: int, bytes: int|float}> $byMime
 * @var array<int, \FileStorage\Model\Entity\FileStorage> $largest
 * @var array<int, \FileStorage\Model\Entity\FileStorage> $recent
 * @var bool $queueLoaded
 */

$this->assign('title', __d('file_storage', 'Dashboard'));

$humanBytes = function (int|float $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $bytes = (float)$bytes;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
};
?>
<h1 class="h3 mb-4"><i class="fas fa-tachometer-alt me-2 text-primary"></i><?= __d('file_storage', 'FileStorage Dashboard') ?></h1>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <?= $this->element('FileStorage.FileStorage/stats_card', [
            'title' => __d('file_storage', 'Total Files'),
            'count' => number_format($totalCount),
            'icon' => 'file',
            'color' => 'primary',
            'link' => $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'index']),
        ]) ?>
    </div>
    <div class="col-md-3 col-sm-6">
        <?= $this->element('FileStorage.FileStorage/stats_card', [
            'title' => __d('file_storage', 'Total Size'),
            'count' => $humanBytes($totalBytes),
            'icon' => 'database',
            'color' => 'info',
        ]) ?>
    </div>
    <div class="col-md-3 col-sm-6">
        <?= $this->element('FileStorage.FileStorage/stats_card', [
            'title' => __d('file_storage', 'Orphan Rows'),
            'count' => number_format($orphanCount),
            'icon' => 'unlink',
            'color' => $orphanCount > 0 ? 'warning' : 'success',
            'link' => $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'cleanup']),
        ]) ?>
    </div>
    <div class="col-md-3 col-sm-6">
        <?= $this->element('FileStorage.FileStorage/stats_card', [
            'title' => __d('file_storage', 'Adapters'),
            'count' => count($byAdapter),
            'icon' => 'plug',
            'color' => 'secondary',
        ]) ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-folder me-2"></i><?= __d('file_storage', 'Top Collections') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Collection') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Files') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Size') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byCollection as $row): ?>
                            <tr>
                                <td><?= h($row['collection']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['count']) ?></td>
                                <td class="text-end"><?= h($humanBytes((int)$row['bytes'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byCollection): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= __d('file_storage', 'No data.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-cube me-2"></i><?= __d('file_storage', 'Top Models') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Model') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Files') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Size') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byModel as $row): ?>
                            <tr>
                                <td><?= h($row['model']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['count']) ?></td>
                                <td class="text-end"><?= h($humanBytes((int)$row['bytes'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byModel): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= __d('file_storage', 'No data.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-plug me-2"></i><?= __d('file_storage', 'Files by Adapter') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Adapter') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Files') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byAdapter as $row): ?>
                            <tr>
                                <td><?= h($row['adapter']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byAdapter): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3"><?= __d('file_storage', 'No data.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-file-code me-2"></i><?= __d('file_storage', 'Top Mime Types') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Mime') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Files') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Size') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byMime as $row): ?>
                            <tr>
                                <td><code><?= h($row['mime_type']) ?></code></td>
                                <td class="text-end"><?= number_format((int)$row['count']) ?></td>
                                <td class="text-end"><?= h($humanBytes((int)$row['bytes'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$byMime): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= __d('file_storage', 'No data.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-weight-hanging me-2"></i><?= __d('file_storage', 'Largest Files') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Filename') ?></th>
                            <th><?= __d('file_storage', 'Collection') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Size') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($largest as $entry): ?>
                            <tr>
                                <td>
                                    <?= $this->Html->link(
                                        h($entry->filename),
                                        ['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'view', $entry->id],
                                        ['escapeTitle' => false],
                                    ) ?>
                                </td>
                                <td><?= h($entry->collection) ?></td>
                                <td class="text-end"><?= h($humanBytes((int)$entry->filesize)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$largest): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= __d('file_storage', 'No data.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-clock me-2"></i><?= __d('file_storage', 'Recent Uploads') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Filename') ?></th>
                            <th><?= __d('file_storage', 'Collection') ?></th>
                            <th><?= __d('file_storage', 'Created') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $entry): ?>
                            <tr>
                                <td>
                                    <?= $this->Html->link(
                                        h($entry->filename),
                                        ['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'view', $entry->id],
                                        ['escapeTitle' => false],
                                    ) ?>
                                </td>
                                <td><?= h($entry->collection) ?></td>
                                <td><?= h($entry->created?->format('Y-m-d H:i') ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= __d('file_storage', 'No uploads yet.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!$queueLoaded): ?>
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <?= __d('file_storage', 'Install {0} to enable background image-variant regeneration from this UI.', '<code>dereuromark/cakephp-queue</code>') ?>
    </div>
<?php endif; ?>
