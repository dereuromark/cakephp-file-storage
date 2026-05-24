<?php declare(strict_types=1);

namespace FileStorage\Controller\Admin;

use Cake\Core\Plugin;
use Cake\Http\Response;
use FileStorage\Model\Table\FileStorageTable;

class FileStorageDashboardController extends FileStorageAppController
{
    /**
     * @var \FileStorage\Model\Table\FileStorageTable
     */
    protected FileStorageTable $FileStorage;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        /** @var \FileStorage\Model\Table\FileStorageTable $table */
        $table = $this->fetchTable('FileStorage.FileStorage');
        $this->FileStorage = $table;
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        $totalCount = $this->FileStorage->find()->count();

        /** @var int|null $totalBytes */
        $totalBytes = $this->FileStorage->find()
            ->select(['total' => 'SUM(filesize)'])
            ->disableHydration()
            ->all()
            ->first()['total'] ?? 0;

        $orphanCount = $this->FileStorage->find()
            ->where(['foreign_key IS' => null])
            ->count();

        // Top 10 collections by file count
        $byCollection = $this->FileStorage->find()
            ->select([
                'collection',
                'count' => 'COUNT(*)',
                'bytes' => 'SUM(filesize)',
            ])
            ->where(['collection IS NOT' => null])
            ->groupBy(['collection'])
            ->orderByDesc('count')
            ->limit(10)
            ->disableHydration()
            ->all()
            ->toList();

        $byModel = $this->FileStorage->find()
            ->select([
                'model',
                'count' => 'COUNT(*)',
                'bytes' => 'SUM(filesize)',
            ])
            ->where(['model IS NOT' => null])
            ->groupBy(['model'])
            ->orderByDesc('count')
            ->limit(10)
            ->disableHydration()
            ->all()
            ->toList();

        $byAdapter = $this->FileStorage->find()
            ->select([
                'adapter',
                'count' => 'COUNT(*)',
            ])
            ->where(['adapter IS NOT' => null])
            ->groupBy(['adapter'])
            ->orderByDesc('count')
            ->disableHydration()
            ->all()
            ->toList();

        // Top mime types — answers "are we storing too many of X?" at a glance.
        $byMime = $this->FileStorage->find()
            ->select([
                'mime_type',
                'count' => 'COUNT(*)',
                'bytes' => 'SUM(filesize)',
            ])
            ->where(['mime_type IS NOT' => null])
            ->groupBy(['mime_type'])
            ->orderByDesc('bytes')
            ->limit(10)
            ->disableHydration()
            ->all()
            ->toList();

        // Top size hogs — answers "where is the disk going?"
        $largest = $this->FileStorage->find()
            ->where(['filesize IS NOT' => null])
            ->orderByDesc('filesize')
            ->limit(10)
            ->all()
            ->toArray();

        $recent = $this->FileStorage->find()
            ->orderByDesc('created')
            ->limit(10)
            ->all()
            ->toArray();

        $this->set(['totalCount' => $totalCount, 'totalBytes' => $totalBytes, 'orphanCount' => $orphanCount, 'byCollection' => $byCollection, 'byModel' => $byModel, 'byAdapter' => $byAdapter, 'byMime' => $byMime, 'largest' => $largest, 'recent' => $recent]);
        $this->set('queueLoaded', Plugin::isLoaded('Queue'));

        return null;
    }
}
