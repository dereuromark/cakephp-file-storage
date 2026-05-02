<?php
/**
 * FileStorage Admin Sidebar Navigation
 *
 * @var \Cake\View\View $this
 * @var bool $mobile When true, render without the position-fixed wrapper
 *   (used inside the mobile offcanvas).
 */

use Cake\Core\Plugin;

$mobile = !empty($mobile);
$controller = $this->getRequest()->getParam('controller');
$action = $this->getRequest()->getParam('action');

$isActive = function (string $c, ?array $actions = null) use ($controller, $action): string {
    if ($controller !== $c) {
        return '';
    }
    if ($actions === null) {
        return 'active';
    }

    return in_array($action, $actions, true) ? 'active' : '';
};

$queueLoaded = Plugin::isLoaded('Queue');
?>
<aside class="fs-sidebar <?= $mobile ? '' : 'd-none d-lg-block' ?>">
    <div class="nav-section">
        <div class="nav-section-title"><?= __d('file_storage', 'Navigation') ?></div>
        <nav class="nav flex-column">
            <a class="nav-link <?= $isActive('FileStorageDashboard') ?>" href="<?= $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorageDashboard', 'action' => 'index']) ?>">
                <i class="fas fa-tachometer-alt"></i>
                <?= __d('file_storage', 'Dashboard') ?>
            </a>
            <a class="nav-link <?= $isActive('FileStorage', ['index', 'view', 'edit']) ?>" href="<?= $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'index']) ?>">
                <i class="fas fa-file"></i>
                <?= __d('file_storage', 'Files') ?>
            </a>
            <a class="nav-link <?= $isActive('FileStorage', ['cleanup']) ?>" href="<?= $this->Url->build(['plugin' => 'FileStorage', 'prefix' => 'Admin', 'controller' => 'FileStorage', 'action' => 'cleanup']) ?>">
                <i class="fas fa-broom"></i>
                <?= __d('file_storage', 'Cleanup') ?>
            </a>
        </nav>
    </div>

    <div class="nav-section">
        <div class="nav-section-title"><?= __d('file_storage', 'Background Jobs') ?></div>
        <nav class="nav flex-column">
            <?php if ($queueLoaded): ?>
                <a class="nav-link" href="<?= $this->Url->build(['plugin' => 'Queue', 'prefix' => 'Admin', 'controller' => 'Queue', 'action' => 'index']) ?>">
                    <i class="fas fa-layer-group"></i>
                    <?= __d('file_storage', 'Queue Admin') ?>
                </a>
            <?php else: ?>
                <span class="nav-link disabled" data-bs-toggle="tooltip" title="<?= h(__d('file_storage', 'Install dereuromark/cakephp-queue to enable background variant regeneration.')) ?>">
                    <i class="fas fa-layer-group"></i>
                    <?= __d('file_storage', 'Queue (not loaded)') ?>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</aside>
