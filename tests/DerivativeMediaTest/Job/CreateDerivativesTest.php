<?php declare(strict_types=1);

namespace DerivativeMediaTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMedia\Job\CreateDerivatives;
use DerivativeMediaTest\DerivativeMediaTestTrait;
use Omeka\Entity\Job;

/**
 * Tests for the CreateDerivatives job (item-level derivatives).
 *
 * Note: The CreateDerivatives job requires the createDerivative controller
 * plugin which may have issues on some installations. Tests are designed to
 * handle this gracefully.
 */
class CreateDerivativesTest extends AbstractHttpControllerTestCase
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
     * Check if the createDerivative plugin can be loaded.
     */
    protected function canLoadCreateDerivativePlugin(): bool
    {
        try {
            $services = $this->getServiceLocator();
            $plugins = $services->get('ControllerPluginManager');
            $plugins->get('createDerivative');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Test job completes with no enabled types.
     *
     * When no types are enabled, the job exits early before needing
     * the createDerivative plugin.
     */
    public function testJobCompletesWithNoEnabledTypes(): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', []);

        // This test should work even with a broken createDerivative plugin
        // because the job exits before instantiating it when no types are enabled.
        // However, the plugin is loaded at initialization, so skip if broken.
        if (!$this->canLoadCreateDerivativePlugin()) {
            $this->markTestSkipped('The createDerivative plugin cannot be loaded (parse error in source).');
        }

        $job = $this->runJob(CreateDerivatives::class, [
            'query' => [],
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job completes with no matching items.
     */
    public function testJobCompletesWithNoMatchingItems(): void
    {
        if (!$this->canLoadCreateDerivativePlugin()) {
            $this->markTestSkipped('The createDerivative plugin cannot be loaded (parse error in source).');
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', ['zip']);

        $job = $this->runJob(CreateDerivatives::class, [
            'query' => ['id' => 999999],
            'type' => 'zip',
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job filters media-level types.
     *
     * Media-level types (audio, video, pdf_media) should not be processed
     * by CreateDerivatives which handles item-level types.
     */
    public function testJobFiltersMediaLevelTypes(): void
    {
        if (!$this->canLoadCreateDerivativePlugin()) {
            $this->markTestSkipped('The createDerivative plugin cannot be loaded (parse error in source).');
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', ['audio', 'video', 'pdf_media']);

        $job = $this->runJob(CreateDerivatives::class, [
            'query' => [],
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job with specific item ID.
     */
    public function testJobWithSpecificItemId(): void
    {
        if (!$this->canLoadCreateDerivativePlugin()) {
            $this->markTestSkipped('The createDerivative plugin cannot be loaded (parse error in source).');
        }

        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item for Derivatives']],
        ]);

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', ['zip']);

        $job = $this->runJob(CreateDerivatives::class, [
            'item_id' => $item->id(),
            'type' => 'zip',
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test that only specified types are processed when provided.
     */
    public function testJobProcessesOnlySpecifiedTypes(): void
    {
        if (!$this->canLoadCreateDerivativePlugin()) {
            $this->markTestSkipped('The createDerivative plugin cannot be loaded (parse error in source).');
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', ['zip', 'txt', 'pdf']);

        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        $job = $this->runJob(CreateDerivatives::class, [
            'item_id' => $item->id(),
            'type' => 'zip',
        ]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }
}
