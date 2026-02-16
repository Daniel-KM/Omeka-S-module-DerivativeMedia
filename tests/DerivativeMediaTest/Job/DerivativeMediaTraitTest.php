<?php declare(strict_types=1);

namespace DerivativeMediaTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMediaTest\DerivativeMediaTestTrait;
use Omeka\Entity\Media;

/**
 * Tests for the DerivativeMediaTrait.
 *
 * Tests storeMetadata logic and isManaged checks using a concrete
 * job class (DerivativeMediaFile) that uses the trait.
 */
class DerivativeMediaTraitTest extends AbstractHttpControllerTestCase
{
    use DerivativeMediaTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test storeMetadata adds derivative data to empty media data.
     */
    public function testStoreMetadataAddsToEmptyData(): void
    {
        $media = new Media();
        $media->setData(null);

        $this->invokeStoreMetadata($media, 'mp3', 'abc123.mp3', 'audio/mpeg');

        $data = $media->getData();
        $this->assertArrayHasKey('derivative', $data);
        $this->assertArrayHasKey('mp3', $data['derivative']);
        $this->assertEquals('abc123.mp3', $data['derivative']['mp3']['filename']);
        $this->assertEquals('audio/mpeg', $data['derivative']['mp3']['type']);
    }

    /**
     * Test storeMetadata adds to existing data without derivative key.
     */
    public function testStoreMetadataAddsToExistingDataWithoutDerivativeKey(): void
    {
        $media = new Media();
        $media->setData(['some_key' => 'some_value']);

        $this->invokeStoreMetadata($media, 'ogg', 'abc123.ogg', 'audio/ogg');

        $data = $media->getData();
        $this->assertEquals('some_value', $data['some_key']);
        $this->assertArrayHasKey('derivative', $data);
        $this->assertEquals('abc123.ogg', $data['derivative']['ogg']['filename']);
    }

    /**
     * Test storeMetadata adds multiple derivatives.
     */
    public function testStoreMetadataAddsMultipleDerivatives(): void
    {
        $media = new Media();
        $media->setData(null);

        $this->invokeStoreMetadata($media, 'mp3', 'abc123.mp3', 'audio/mpeg');
        $this->invokeStoreMetadata($media, 'ogg', 'abc123.ogg', 'audio/ogg');

        $data = $media->getData();
        $this->assertCount(2, $data['derivative']);
        $this->assertArrayHasKey('mp3', $data['derivative']);
        $this->assertArrayHasKey('ogg', $data['derivative']);
    }

    /**
     * Test storeMetadata removes derivative when basename is null.
     */
    public function testStoreMetadataRemovesDerivativeWhenNull(): void
    {
        $media = new Media();
        $media->setData([
            'derivative' => [
                'mp3' => ['filename' => 'abc123.mp3', 'type' => 'audio/mpeg'],
                'ogg' => ['filename' => 'abc123.ogg', 'type' => 'audio/ogg'],
            ],
        ]);

        $this->invokeStoreMetadata($media, 'mp3', null, null);

        $data = $media->getData();
        $this->assertArrayNotHasKey('mp3', $data['derivative']);
        $this->assertArrayHasKey('ogg', $data['derivative']);
    }

    /**
     * Test storeMetadata removes derivative when basename is empty.
     */
    public function testStoreMetadataRemovesDerivativeWhenEmpty(): void
    {
        $media = new Media();
        $media->setData([
            'derivative' => [
                'mp3' => ['filename' => 'abc123.mp3', 'type' => 'audio/mpeg'],
            ],
        ]);

        $this->invokeStoreMetadata($media, 'mp3', '', '');

        $data = $media->getData();
        $this->assertArrayNotHasKey('mp3', $data['derivative']);
    }

    /**
     * Test isManaged returns true for audio media.
     */
    public function testIsManagedReturnsTrueForAudio(): void
    {
        $media = $this->createMockMedia('audio/mpeg', true, 'file');
        $this->assertTrue($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns true for video media.
     */
    public function testIsManagedReturnsTrueForVideo(): void
    {
        $media = $this->createMockMedia('video/mp4', true, 'file');
        $this->assertTrue($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns true for pdf media.
     */
    public function testIsManagedReturnsTrueForPdf(): void
    {
        $media = $this->createMockMedia('application/pdf', true, 'file');
        $this->assertTrue($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns false for image media.
     */
    public function testIsManagedReturnsFalseForImage(): void
    {
        $media = $this->createMockMedia('image/jpeg', true, 'file');
        $this->assertFalse($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns false for media without original.
     */
    public function testIsManagedReturnsFalseWithoutOriginal(): void
    {
        $media = $this->createMockMedia('audio/mpeg', false, 'file');
        $this->assertFalse($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns false for non-file renderer.
     */
    public function testIsManagedReturnsFalseForNonFileRenderer(): void
    {
        $media = $this->createMockMedia('audio/mpeg', true, 'youtube');
        $this->assertFalse($this->invokeIsManaged($media));
    }

    /**
     * Test isManaged returns false when media type is null.
     */
    public function testIsManagedReturnsFalseWithNullMediaType(): void
    {
        $media = $this->createMockMedia(null, true, 'file');
        $this->assertFalse($this->invokeIsManaged($media));
    }

    /**
     * Create a mock Media entity for isManaged tests.
     */
    protected function createMockMedia(?string $mediaType, bool $hasOriginal, string $renderer): Media
    {
        $media = new Media();
        $media->setMediaType($mediaType);
        $media->setHasOriginal($hasOriginal);
        $media->setRenderer($renderer);
        return $media;
    }

    /**
     * Invoke the protected storeMetadata method from DerivativeMediaTrait.
     */
    protected function invokeStoreMetadata(Media $media, string $folder, ?string $basename, ?string $mediaType): void
    {
        $job = $this->createTraitJobInstance();
        $reflection = new \ReflectionMethod($job, 'storeMetadata');
        $reflection->setAccessible(true);
        $reflection->invokeArgs($job, [$media, $folder, $basename, $mediaType]);
    }

    /**
     * Invoke the protected isManaged method from DerivativeMediaTrait.
     */
    protected function invokeIsManaged(Media $media): bool
    {
        $job = $this->createTraitJobInstance();
        $reflection = new \ReflectionMethod($job, 'isManaged');
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($job, [$media]);
    }

    /**
     * Create a DerivativeMediaFile job instance to access trait methods.
     */
    protected function createTraitJobInstance(): \DerivativeMedia\Job\DerivativeMediaFile
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $auth = $services->get('Omeka\AuthenticationService');

        $job = new \Omeka\Entity\Job();
        $job->setStatus(\Omeka\Entity\Job::STATUS_STARTING);
        $job->setClass(\DerivativeMedia\Job\DerivativeMediaFile::class);
        $job->setArgs([]);
        $job->setOwner($auth->getIdentity());

        $entityManager->persist($job);
        $entityManager->flush();

        return new \DerivativeMedia\Job\DerivativeMediaFile($job, $services);
    }
}
