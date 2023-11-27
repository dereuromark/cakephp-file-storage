<?php
/**
 * @var \App\View\AppView $this
 * @var \FileStorage\Model\Entity\FileStorage $fileStorage
 */

use Brick\VarExporter\VarExporter;

?>
<div class="row">
    <aside class="column actions large-3 medium-4 col-sm-4 col-xs-12">
        <ul class="side-nav nav nav-pills flex-column">
            <li class="nav-item heading"><?= __('Actions') ?></li>
            <li class="nav-item"><?= $this->Html->link(__('Edit {0}', __('File Storage')), ['action' => 'edit', $fileStorage->id], ['class' => 'side-nav-item']) ?></li>
            <li class="nav-item"><?= $this->Form->postLink(__('Delete {0}', __('File Storage')), ['action' => 'delete', $fileStorage->id], ['confirm' => __('Are you sure you want to delete # {0}?', $fileStorage->id), 'class' => 'side-nav-item']) ?></li>
            <li class="nav-item"><?= $this->Html->link(__('List {0}', __('File Storage')), ['action' => 'index'], ['class' => 'side-nav-item']) ?></li>
        </ul>
    </aside>
    <div class="column-responsive column-80 content large-9 medium-8 col-sm-8 col-xs-12">
        <div class="fileStorage view content">
            <h2><?= h($fileStorage->filename) ?></h2>

            <table class="table table-striped">
                <tr>
                    <th><?= __('Model') ?></th>
                    <td><?= h($fileStorage->model) ?> : <?= $this->Number->format($fileStorage->foreign_key) ?></td>
                </tr>
                <tr>
                    <th><?= __('Collection') ?></th>
                    <td><?= h($fileStorage->collection) ?></td>
                </tr>
                <tr>
                    <th><?= __('Filename') ?></th>
                    <td><?= h($fileStorage->filename) ?></td>
                </tr>
                <tr>
                    <th><?= __('Mime Type') ?></th>
                    <td><?= h($fileStorage->mime_type) ?></td>
                </tr>
                <tr>
                    <th><?= __('Extension') ?></th>
                    <td><?= h($fileStorage->extension) ?></td>
                </tr>
                <tr>
                    <th><?= __('Path') ?></th>
                    <td><?= h($fileStorage->path) ?></td>
                </tr>
                <tr>
                    <th><?= __('Adapter') ?></th>
                    <td><?= h($fileStorage->adapter) ?></td>
                </tr>
                <tr>
                    <th><?= __('Variants') ?></th>
                    <td><pre><?= VarExporter::export(h($fileStorage->variants), VarExporter::TRAILING_COMMA_IN_ARRAY); ?></pre></td>
                </tr>
                <tr>
                    <th><?= __('Metadata') ?></th>
                    <td><pre><?= VarExporter::export(h($fileStorage->metadata), VarExporter::TRAILING_COMMA_IN_ARRAY); ?></pre></td>
                </tr>
                <tr>
                    <th><?= __('User') ?></th>
                    <td><?= h($fileStorage->user_id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Filesize') ?></th>
                    <td><?= $this->Number->format($fileStorage->filesize) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= $this->Time->nice($fileStorage->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= $this->Time->nice($fileStorage->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
