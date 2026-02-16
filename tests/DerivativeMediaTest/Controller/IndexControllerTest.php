<?php declare(strict_types=1);

namespace DerivativeMediaTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMediaTest\DerivativeMediaTestTrait;

/**
 * Tests for the DerivativeMedia controller.
 */
class IndexControllerTest extends AbstractHttpControllerTestCase
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
     * Test that derivative route exists and matches controller.
     */
    public function testDerivativeRouteExists(): void
    {
        // Create an item to have a valid ID.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        $this->dispatch('/derivative/' . $item->id() . '/pdf');
        $this->assertControllerName('DerivativeMedia\Controller\Index');
        $this->assertActionName('index');
    }

    /**
     * Test that derivative route rejects invalid type.
     */
    public function testDerivativeRouteRejectsInvalidType(): void
    {
        $this->dispatch('/derivative/1/invalid_type');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test that derivative route requires numeric ID.
     */
    public function testDerivativeRouteRequiresNumericId(): void
    {
        $this->dispatch('/derivative/abc/pdf');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test that derivative route accepts valid type constraints.
     *
     * @dataProvider validTypesProvider
     */
    public function testDerivativeRouteAcceptsValidTypes(string $type): void
    {
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        $this->dispatch('/derivative/' . $item->id() . '/' . $type);
        // Should reach the controller (not 404 from router).
        $this->assertControllerName('DerivativeMedia\Controller\Index');
    }

    public function validTypesProvider(): array
    {
        return [
            'alto' => ['alto'],
            'iiif-2' => ['iiif-2'],
            'iiif-3' => ['iiif-3'],
            'pdf' => ['pdf'],
            'pdf2xml' => ['pdf2xml'],
            'txt' => ['txt'],
            'text' => ['text'],
            'zip' => ['zip'],
            'zipm' => ['zipm'],
            'zipo' => ['zipo'],
        ];
    }

    /**
     * Test that media-level types are rejected by the controller.
     *
     * Audio and video types are not in the route constraints, so the
     * router itself returns a 404.
     */
    public function testMediaLevelTypesNotInRoute(): void
    {
        $this->dispatch('/derivative/1/audio');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test that nonexistent resource returns error.
     */
    public function testNonexistentResourceReturnsError(): void
    {
        // Enable pdf type for the test.
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', ['pdf']);

        $this->dispatch('/derivative/999999/pdf');
        // Should return error status (404 from API or 500 from controller).
        $status = $this->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $status);
    }

    /**
     * Test that disabled type returns 400.
     */
    public function testDisabledTypeReturnsBadRequest(): void
    {
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        // Ensure the type is disabled.
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('derivativemedia_enable', []);

        $this->dispatch('/derivative/' . $item->id() . '/pdf');
        $this->assertResponseStatusCode(400);
    }
}
