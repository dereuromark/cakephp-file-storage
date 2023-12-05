<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface[]|\Cake\Collection\CollectionInterface $fileStorage
 */
?>
<nav class="actions large-3 medium-4 columns col-sm-4 col-xs-12" id="actions-sidebar">
    <ul class="side-nav nav nav-pills flex-column">
        <li class="nav-item heading"><?= __('Actions') ?></li>
        <li class="nav-item">
            <?= $this->Html->link(__('New {0}', __('File Storage')), ['action' => 'add'], ['class' => 'nav-link']) ?>
        </li>
    </ul>
</nav>
<div class="fileStorage index content large-9 medium-8 columns col-sm-8 col-12">

    <h2><?= __('File Storage') ?></h2>

    <div class="">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('model') ?></th>
                    <th><?= $this->Paginator->sort('collection') ?></th>
                    <th><?= $this->Paginator->sort('filename') ?></th>
                    <th><?= $this->Paginator->sort('filesize') ?></th>
                    <th><?= $this->Paginator->sort('mime_type') ?></th>
                    <th><?= $this->Paginator->sort('created', null, ['direction' => 'desc']) ?></th>
                    <th><?= $this->Paginator->sort('modified', null, ['direction' => 'desc']) ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fileStorage as $fileStorage): ?>
                <tr>
                    <td><?= h($fileStorage->model) ?>:<?= $this->Number->format($fileStorage->foreign_key) ?></td>
                    <td><?= h($fileStorage->collection) ?></td>
                    <td>
                        <?= h($fileStorage->filename) ?>
                        <div><small><?= h($fileStorage->path) ?></small></div>
                    </td>
                    <td>
                        <?= $this->Number->toReadableSize($fileStorage->filesize) ?>
                    </td>
                    <td>
                        <?= h($fileStorage->mime_type) ?>
                        <div><small>Ext: <?= h($fileStorage->extension) ?></small></div>
                    </td>
                    <td><?= $this->Time->nice($fileStorage->created) ?></td>
                    <td><?= $this->Time->nice($fileStorage->modified) ?></td>

                    <td class="actions">
                        <?= $this->Html->link(Plugin::isLoaded('Tools') ? $this->Icon->render('view') : __('View'), ['action' => 'view', $fileStorage->id], ['escapeTitle' => false]); ?>
                        <?= $this->Html->link(Plugin::isLoaded('Tools') ? $this->Icon->render('edit') : __('Edit'), ['action' => 'edit', $fileStorage->id], ['escapeTitle' => false]); ?>
                        <?= $this->Form->postLink(Plugin::isLoaded('Tools') ? $this->Icon->render('delete') : __('Delete'), ['action' => 'delete', $fileStorage->id], ['escapeTitle' => false, 'confirm' => __('Are you sure you want to delete # {0}?', $fileStorage->id)]); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php echo $this->element('Tools.pagination'); ?>
</div>
