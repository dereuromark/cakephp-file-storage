<?php
/**
 * @var \Cake\View\View $this
 * @var \FileStorage\Model\Entity\FileStorage $fileStorage
 * @var bool $queueLoaded
 */

use Brick\VarExporter\VarExporter;
use Cake\Core\Configure;

$this->assign('title', $fileStorage->filename ?? __d('file_storage', 'File'));

$humanBytes = function (int|float|null $bytes): string {
    if ($bytes === null) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $bytes = (float)$bytes;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
};

// Resolve the configured Flysystem adapter once so the variants table can show
// existence + on-disk size without doing N queries from inside the template.
$fileStorageService = Configure::read('FileStorage.behaviorConfig.fileStorage');
$adapter = null;
if ($fileStorageService !== null && $fileStorage->adapter !== null) {
    try {
        $adapter = $fileStorageService->getStorage((string)$fileStorage->adapter);
    } catch (\Throwable $e) {
        $adapter = null;
    }
}

$probe = function (?string $path) use ($adapter): array {
    if ($adapter === null || $path === null || $path === '') {
        return ['exists' => false, 'size' => null];
    }
    try {
        if (!$adapter->fileExists($path)) {
            return ['exists' => false, 'size' => null];
        }
        $size = $adapter->fileSize($path)->fileSize();
    } catch (\Throwable $e) {
        return ['exists' => false, 'size' => null];
    }

    return ['exists' => true, 'size' => $size];
};

$isImage = $fileStorage->mime_type !== null && str_starts_with((string)$fileStorage->mime_type, 'image/');
$mainProbe = $probe($fileStorage->path);

$variants = (array)$fileStorage->variants;
?>
<h1 class="h3 mb-4 d-flex justify-content-between align-items-start gap-3">
    <span>
        <i class="fas fa-file me-2 text-primary"></i><?= h($fileStorage->filename) ?>
    </span>
    <span class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="fas fa-arrow-left me-1"></i><?= __d('file_storage', 'Back to list') ?>
        </a>
        <a class="btn btn-sm btn-primary" href="<?= $this->Url->build(['action' => 'download', $fileStorage->id]) ?>">
            <i class="fas fa-download me-1"></i><?= __d('file_storage', 'Download') ?>
        </a>
        <a class="btn btn-sm btn-outline-primary" href="<?= $this->Url->build(['action' => 'edit', $fileStorage->id]) ?>">
            <i class="fas fa-pen me-1"></i><?= __d('file_storage', 'Edit') ?>
        </a>
        <?php if ($queueLoaded): ?>
            <?= $this->Form->postButton(
                '<i class="fas fa-sync me-1"></i>' . __d('file_storage', 'Regenerate variants'),
                ['action' => 'regenerateVariants', $fileStorage->id],
                [
                    'class' => 'btn btn-sm btn-outline-info',
                    'escapeTitle' => false,
                    'form' => [
                        'class' => 'd-inline',
                        'data-confirm-message' => __d('file_storage', 'Queue a variant regeneration job for {0}?', $fileStorage->filename),
                    ],
                ],
            ) ?>
        <?php endif; ?>
        <?= $this->Form->postButton(
            '<i class="fas fa-trash me-1"></i>' . __d('file_storage', 'Delete'),
            ['action' => 'delete', $fileStorage->id],
            [
                'class' => 'btn btn-sm btn-outline-danger',
                'escapeTitle' => false,
                'form' => [
                    'class' => 'd-inline',
                    'data-confirm-message' => __d('file_storage', 'Delete {0}? This cannot be undone.', $fileStorage->filename),
                ],
            ],
        ) ?>
    </span>
</h1>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i><?= __d('file_storage', 'Details') ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="w-25"><?= __d('file_storage', 'Model') ?></th>
                            <td><code><?= h($fileStorage->model) ?></code> : <?= h((string)$fileStorage->foreign_key) ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Collection') ?></th>
                            <td><?= h($fileStorage->collection) ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Mime / Extension') ?></th>
                            <td><code><?= h($fileStorage->mime_type) ?></code> &middot; <?= h($fileStorage->extension) ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Adapter') ?></th>
                            <td><?= h($fileStorage->adapter) ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Path') ?></th>
                            <td><code class="small"><?= h($fileStorage->path) ?></code></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'DB filesize') ?></th>
                            <td><?= h($humanBytes($fileStorage->filesize)) ?> <span class="text-muted small">(<?= number_format((int)$fileStorage->filesize) ?> <?= __d('file_storage', 'bytes') ?>)</span></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Hash') ?></th>
                            <td><code class="small"><?= h($fileStorage->hash) ?></code></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Created') ?></th>
                            <td><?= h($fileStorage->created?->format('Y-m-d H:i:s') ?? '') ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'Modified') ?></th>
                            <td><?= h($fileStorage->modified?->format('Y-m-d H:i:s') ?? '') ?></td>
                        </tr>
                        <tr>
                            <th><?= __d('file_storage', 'User') ?></th>
                            <td><?= h((string)$fileStorage->user_id) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-images me-2"></i><?= __d('file_storage', 'Variants') ?></span>
                <span class="badge bg-secondary"><?= count($variants) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?= __d('file_storage', 'Preview') ?></th>
                            <th><?= __d('file_storage', 'Variant') ?></th>
                            <th><?= __d('file_storage', 'Path') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Size on disk') ?></th>
                            <th class="text-center"><?= __d('file_storage', 'State') ?></th>
                            <th class="text-end"><?= __d('file_storage', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="width:80px;">
                                <?php if ($isImage && $mainProbe['exists']): ?>
                                    <img src="<?= $this->Url->build(['action' => 'download', $fileStorage->id, '?' => ['inline' => 1]]) ?>"
                                        alt="<?= h($fileStorage->filename) ?>"
                                        style="max-width:64px;max-height:64px;object-fit:contain;border-radius:4px;">
                                <?php else: ?>
                                    <i class="fas fa-file fa-2x text-muted"></i>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= __d('file_storage', 'main') ?></strong></td>
                            <td><code class="small"><?= h($fileStorage->path) ?></code></td>
                            <td class="text-end"><?= h($humanBytes($mainProbe['size'])) ?></td>
                            <td class="text-center">
                                <?php if ($mainProbe['exists']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> <?= __d('file_storage', 'present') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> <?= __d('file_storage', 'missing') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'download', $fileStorage->id]) ?>"
                                    title="<?= h(__d('file_storage', 'Download')) ?>">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                    href="<?= $this->Url->build(['action' => 'download', $fileStorage->id, '?' => ['inline' => 1]]) ?>"
                                    title="<?= h(__d('file_storage', 'Open inline')) ?>">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php foreach ($variants as $name => $details): ?>
                            <?php
                            $variantPath = is_array($details) ? ($details['path'] ?? null) : null;
                            $vProbe = $probe(is_string($variantPath) ? $variantPath : null);
                            $variantIsImage = $isImage; // assume same family as main
                            ?>
                            <tr>
                                <td>
                                    <?php if ($variantIsImage && $vProbe['exists']): ?>
                                        <img src="<?= $this->Url->build(['action' => 'download', $fileStorage->id, '?' => ['inline' => 1, 'variant' => $name]]) ?>"
                                            alt="<?= h((string)$name) ?>"
                                            style="max-width:64px;max-height:64px;object-fit:contain;border-radius:4px;">
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string)$name) ?></td>
                                <td><code class="small"><?= h((string)$variantPath) ?></code></td>
                                <td class="text-end"><?= h($humanBytes($vProbe['size'])) ?></td>
                                <td class="text-center">
                                    <?php if ($vProbe['exists']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> <?= __d('file_storage', 'present') ?></span>
                                    <?php elseif ($variantPath): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times"></i> <?= __d('file_storage', 'missing') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= __d('file_storage', 'no path') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($variantPath): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'download', $fileStorage->id, '?' => ['variant' => $name]]) ?>"
                                            title="<?= h(__d('file_storage', 'Download')) ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                            href="<?= $this->Url->build(['action' => 'download', $fileStorage->id, '?' => ['inline' => 1, 'variant' => $name]]) ?>"
                                            title="<?= h(__d('file_storage', 'Open inline')) ?>">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$variants): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    <?= __d('file_storage', 'No variants generated.') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-code me-2"></i><?= __d('file_storage', 'Variants (raw)') ?></div>
            <div class="card-body p-2">
                <pre class="small mb-0"><?= h(VarExporter::export($fileStorage->variants ?: [], VarExporter::TRAILING_COMMA_IN_ARRAY)) ?></pre>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-tags me-2"></i><?= __d('file_storage', 'Metadata') ?></div>
            <div class="card-body p-2">
                <pre class="small mb-0"><?= h(VarExporter::export($fileStorage->metadata ?: [], VarExporter::TRAILING_COMMA_IN_ARRAY)) ?></pre>
            </div>
        </div>
    </div>
</div>
