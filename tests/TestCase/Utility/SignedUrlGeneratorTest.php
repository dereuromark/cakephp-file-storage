<?php
declare(strict_types=1);

namespace FileStorage\Test\TestCase\Utility;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use FileStorage\Model\Entity\FileStorage;
use FileStorage\Utility\SignedUrlGenerator;

/**
 * FileStorage\Utility\SignedUrlGenerator Test Case
 */
class SignedUrlGeneratorTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set a test salt for Security::getSalt()
        Security::setSalt('test-salt-for-signed-url-generator-tests-1234567890');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('FileStorage.signatureSecret');
    }

    /**
     * Test generate method
     *
     * @return void
     */
    public function testGenerate(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $expires = strtotime('+1 hour');

        $result = SignedUrlGenerator::generate($fileStorage, [
            'expires' => $expires,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('expires', $result);
        $this->assertEquals($expires, $result['expires']);
        $this->assertIsString($result['signature']);
        $this->assertEquals(64, strlen($result['signature'])); // SHA256 hex = 64 chars
    }

    /**
     * Test generate without expiration
     *
     * @return void
     */
    public function testGenerateWithoutExpiration(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result = SignedUrlGenerator::generate($fileStorage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('expires', $result);
        $this->assertNull($result['expires']);
        $this->assertIsString($result['signature']);
    }

    /**
     * Test generate with custom secret
     *
     * @return void
     */
    public function testGenerateWithCustomSecret(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result1 = SignedUrlGenerator::generate($fileStorage, [
            'secret' => 'secret-1',
        ]);

        $result2 = SignedUrlGenerator::generate($fileStorage, [
            'secret' => 'secret-2',
        ]);

        // Different secrets should produce different signatures
        $this->assertNotEquals($result1['signature'], $result2['signature']);
    }

    /**
     * Test generate uses configured secret
     *
     * @return void
     */
    public function testGenerateUsesConfiguredSecret(): void
    {
        Configure::write('FileStorage.signatureSecret', 'configured-secret');

        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result = SignedUrlGenerator::generate($fileStorage);

        // Verify it used the configured secret
        $expected = SignedUrlGenerator::generate($fileStorage, [
            'secret' => 'configured-secret',
        ]);

        $this->assertEquals($expected['signature'], $result['signature']);

        Configure::delete('FileStorage.signatureSecret');
    }

    /**
     * Test verify accepts valid signature
     *
     * @return void
     */
    public function testVerifyAcceptsValidSignature(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $expires = strtotime('+1 hour');

        $result = SignedUrlGenerator::generate($fileStorage, [
            'expires' => $expires,
        ]);

        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            ['expires' => $expires],
        );

        $this->assertTrue($isValid);
    }

    /**
     * Test verify rejects invalid signature
     *
     * @return void
     */
    public function testVerifyRejectsInvalidSignature(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            'invalid-signature-abc123',
            [],
        );

        $this->assertFalse($isValid);
    }

    /**
     * Test verify rejects expired signature
     *
     * @return void
     */
    public function testVerifyRejectsExpiredSignature(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $expires = strtotime('-1 hour'); // Expired 1 hour ago

        $result = SignedUrlGenerator::generate($fileStorage, [
            'expires' => $expires,
        ]);

        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            ['expires' => $expires],
        );

        $this->assertFalse($isValid);
    }

    /**
     * Test verify invalidates signature when file changes
     *
     * @return void
     */
    public function testVerifyInvalidatesSignatureWhenFileChanges(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result = SignedUrlGenerator::generate($fileStorage);

        // Simulate file modification
        $fileStorage->modified = new DateTime('2025-01-02 12:00:00');

        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            [],
        );

        $this->assertFalse($isValid);
    }

    /**
     * Test verify works without expiration
     *
     * @return void
     */
    public function testVerifyWorksWithoutExpiration(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result = SignedUrlGenerator::generate($fileStorage);

        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            [],
        );

        $this->assertTrue($isValid);
    }

    /**
     * Test verify respects custom secret
     *
     * @return void
     */
    public function testVerifyRespectsCustomSecret(): void
    {
        $fileStorage = new FileStorage([
            'id' => 'test-uuid-123',
            'path' => 'files/test.jpg',
            'modified' => new DateTime('2025-01-01 12:00:00'),
        ]);

        $result = SignedUrlGenerator::generate($fileStorage, [
            'secret' => 'my-secret',
        ]);

        // Verify with correct secret
        $isValid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            ['secret' => 'my-secret'],
        );
        $this->assertTrue($isValid);

        // Verify with wrong secret
        $isInvalid = SignedUrlGenerator::verify(
            $fileStorage,
            $result['signature'],
            ['secret' => 'wrong-secret'],
        );
        $this->assertFalse($isInvalid);
    }
}
