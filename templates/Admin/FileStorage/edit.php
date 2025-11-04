<?php
/**
 * @var \App\View\AppView $this
 * @var \FileStorage\Model\Entity\FileStorage $fileStorage
 */
?>
<div class="row">
    <aside class="column large-3 medium-4 columns col-sm-4 col-12">
        <ul class="side-nav nav nav-pills flex-column">
            <li class="nav-item heading"><?= __('Actions') ?></li>
            <li class="nav-item"><?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $fileStorage->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $fileStorage->id), 'class' => 'side-nav-item']
                ) ?></li>
            <li class="nav-item"><?= $this->Html->link(__('List File Storage'), ['action' => 'index'], ['class' => 'side-nav-item']) ?></li>
        </ul>
    </aside>
    <div class="column-responsive column-80 form large-9 medium-8 columns col-sm-8 col-12">
        <div class="fileStorage form content">
            <h2><?= __('File Storage') ?></h2>

            <?= $this->Form->create($fileStorage) ?>
            <fieldset>
                <legend><?= __('Edit File Storage') ?></legend>
                <?php
                echo $this->Form->control('model');
                echo $this->Form->control('foreign_key');
                echo $this->Form->control('collection');
                echo $this->Form->control('filename');
                echo $this->Form->control('extension');
                echo $this->Form->control('mime_type');
                echo $this->Form->control('filesize');
                echo $this->Form->control('path');
                echo $this->Form->control('adapter');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
