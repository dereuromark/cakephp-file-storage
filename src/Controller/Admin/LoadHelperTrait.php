<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use Cake\Core\Plugin;

/**
 * Soft-dependency helper bootstrap for the FileStorage admin views.
 *
 * Mirrors the pattern used by `cakephp-queue` and `cakephp-tags`: helpers from
 * sibling utility plugins (Tools, Shim, Templating) are loaded only when those
 * plugins are present, so the admin UI works with or without them.
 */
trait LoadHelperTrait
{
    /**
     * @return void
     */
    protected function loadFileStorageHelpers(): void
    {
        $helpers = [];

        if (Plugin::isLoaded('Tools')) {
            $helpers[] = 'Tools.Time';
            $helpers[] = 'Tools.Format';
        } else {
            $helpers[] = 'Time';
            $helpers[] = 'Number';
        }

        if (Plugin::isLoaded('Templating')) {
            $helpers[] = 'Templating.Icon';
        }

        $this->viewBuilder()->addHelpers($helpers);
    }
}
