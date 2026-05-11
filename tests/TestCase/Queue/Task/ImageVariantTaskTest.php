<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Queue\Task;

use Cake\Datasource\ConnectionManager;
use FileStorage\Queue\Task\ImageVariantTask;
use FileStorage\Test\TestCase\FileStorageTestCase;
use Queue\Model\QueueException;

/**
 * @uses \FileStorage\Queue\Task\ImageVariantTask
 */
class ImageVariantTaskTest extends FileStorageTestCase
{
 /**
  * Only the FileStorage fixture is loaded. The Queue plugin's
  * `queued_jobs` table is not part of this test app's schema, but the
  * Task base class fetches it eagerly via LocatorAwareTrait — we side-
  * step by creating the table manually as a one-off in setUp(). This
  * lets the failure-path tests below (validation + soft no-op) work
  * without dragging in the full Queue plugin migrations setup.
  *
  * @var array<string>
  */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Conjure a minimal `queued_jobs` table so the Task base class's
        // LocatorAwareTrait lookup succeeds. A full Queue plugin migration
        // isn't needed here — the assertions only exercise validation +
        // missing-entity branches and never persist a job.
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'CREATE TABLE IF NOT EXISTS queued_jobs ('
            . '  id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . '  job_task VARCHAR(255),'
            . '  data TEXT'
            . ')',
        );
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('DROP TABLE IF EXISTS queued_jobs');
        parent::tearDown();
    }

    /**
     * Missing `id` data is a misconfigured payload — must throw so the
     * worker can move the job to its dead-letter / retry path instead
     * of silently no-op'ing.
     *
     * @return void
     */
    public function testMissingIdThrows(): void
    {
        $task = new ImageVariantTask();

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('id');
        $task->run([], 1);
    }

    /**
     * Missing `operations` is also a misconfigured payload — same reason.
     *
     * @return void
     */
    public function testMissingOperationsThrows(): void
    {
        $task = new ImageVariantTask();

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('operations');
        $task->run(['id' => 1], 1);
    }

    /**
     * A job for an entity that no longer exists must soft-fail (no exception,
     * no log spam). This matches the regeneration use case: an admin queues
     * a batch, deletes a file mid-flight, and the corresponding job arrives
     * to find nothing. That's expected, not an error.
     *
     * @return void
     */
    public function testMissingEntityIsNoOp(): void
    {
        $task = new ImageVariantTask();

        $task->run([
            'id' => 999999,
            'operations' => ['thumbnail' => ['width' => 50]],
        ], 1);

        // No exception, no assertion needed beyond reaching this point.
        $this->assertTrue(true);
    }
}
