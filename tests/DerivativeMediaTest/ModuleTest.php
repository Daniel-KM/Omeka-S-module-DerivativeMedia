<?php declare(strict_types=1);

namespace DerivativeMediaTest;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMedia\Module;

/**
 * Tests for the DerivativeMedia Module class.
 */
class ModuleTest extends AbstractHttpControllerTestCase
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
     * Test that the DERIVATIVES constant contains expected types.
     */
    public function testDerivativesConstantContainsExpectedTypes(): void
    {
        $expectedTypes = [
            'audio', 'video', 'pdf_media',
            'alto', 'iiif-2', 'iiif-3', 'pdf', 'pdf2xml',
            'txt', 'text', 'zip', 'zipm', 'zipo',
        ];
        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, Module::DERIVATIVES, "Missing type: $type");
        }
    }

    /**
     * Test that each derivative type has required keys.
     */
    public function testDerivativeTypesHaveRequiredKeys(): void
    {
        foreach (Module::DERIVATIVES as $type => $config) {
            $this->assertArrayHasKey('mode', $config, "Type '$type' missing 'mode'");
            $this->assertArrayHasKey('level', $config, "Type '$type' missing 'level'");
            $this->assertArrayHasKey('multiple', $config, "Type '$type' missing 'multiple'");
        }
    }

    /**
     * Test that media-level types have 'static' mode.
     */
    public function testMediaLevelTypesAreStatic(): void
    {
        $mediaTypes = ['audio', 'video', 'pdf_media'];
        foreach ($mediaTypes as $type) {
            $this->assertEquals('static', Module::DERIVATIVES[$type]['mode'], "Media type '$type' should be 'static'");
            $this->assertEquals('media', Module::DERIVATIVES[$type]['level'], "Type '$type' should be 'media' level");
        }
    }

    /**
     * Test that item-level types have required item keys.
     */
    public function testItemLevelTypesHaveItemKeys(): void
    {
        foreach (Module::DERIVATIVES as $type => $config) {
            if ($config['level'] !== 'item') {
                continue;
            }
            $this->assertArrayHasKey('mediatype', $config, "Item type '$type' missing 'mediatype'");
            $this->assertArrayHasKey('extension', $config, "Item type '$type' missing 'extension'");
            $this->assertArrayHasKey('dir', $config, "Item type '$type' missing 'dir'");
        }
    }

    /**
     * Test that derivative modes are valid.
     */
    public function testDerivativeModesAreValid(): void
    {
        $validModes = ['static', 'dynamic', 'live', 'dynamic_live'];
        foreach (Module::DERIVATIVES as $type => $config) {
            $this->assertContains($config['mode'], $validModes, "Type '$type' has invalid mode '{$config['mode']}'");
        }
    }

    /**
     * Test that derivative levels are valid.
     */
    public function testDerivativeLevelsAreValid(): void
    {
        $validLevels = ['item', 'media'];
        foreach (Module::DERIVATIVES as $type => $config) {
            $this->assertContains($config['level'], $validLevels, "Type '$type' has invalid level '{$config['level']}'");
        }
    }

    /**
     * Test that module is active after bootstrap.
     */
    public function testModuleIsActive(): void
    {
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('DerivativeMedia');
        $this->assertNotNull($module, 'DerivativeMedia module should be registered');
        $this->assertEquals('active', $module->getState(), 'DerivativeMedia module should be active');
    }

    /**
     * Test that the derivative controller is accessible via ACL (public).
     */
    public function testDerivativeControllerIsPublic(): void
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        // The controller should be accessible by anyone (null role).
        $this->assertTrue(
            $acl->isAllowed(null, 'DerivativeMedia\Controller\Index'),
            'DerivativeMedia controller should be publicly accessible'
        );
    }

    /**
     * Test that controller plugins are registered.
     */
    public function testControllerPluginsAreRegistered(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');

        $this->assertTrue($plugins->has('checkFfmpeg'), 'checkFfmpeg plugin should be registered');
        $this->assertTrue($plugins->has('checkGhostscript'), 'checkGhostscript plugin should be registered');
        $this->assertTrue($plugins->has('createDerivative'), 'createDerivative plugin should be registered');
    }

    /**
     * Test that view helpers are registered.
     */
    public function testViewHelpersAreRegistered(): void
    {
        $services = $this->getServiceLocator();
        $helpers = $services->get('ViewHelperManager');

        $this->assertTrue($helpers->has('derivatives'), 'derivatives helper should be registered');
        $this->assertTrue($helpers->has('derivativeList'), 'derivativeList helper should be registered');
    }

    /**
     * Test that view helper aliases are registered.
     */
    public function testViewHelperAliasesAreRegistered(): void
    {
        $services = $this->getServiceLocator();
        $helpers = $services->get('ViewHelperManager');

        $this->assertTrue($helpers->has('derivativeMedia'), 'derivativeMedia alias should be registered');
        $this->assertTrue($helpers->has('hasDerivative'), 'hasDerivative alias should be registered');
    }

    /**
     * Test that file renderers are registered.
     */
    public function testFileRenderersAreRegistered(): void
    {
        $services = $this->getServiceLocator();
        $renderers = $services->get('Omeka\Media\FileRenderer\Manager');

        $this->assertTrue($renderers->has('audio'), 'Audio renderer should be registered');
        $this->assertTrue($renderers->has('video'), 'Video renderer should be registered');
    }

    /**
     * Test that default settings are defined.
     */
    public function testDefaultSettingsAreDefined(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $moduleConfig = $config['derivativemedia']['settings'] ?? [];

        $this->assertArrayHasKey('derivativemedia_enable', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_update', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_max_size_live', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_converters_audio', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_converters_video', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_converters_pdf', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_append_original_audio', $moduleConfig);
        $this->assertArrayHasKey('derivativemedia_append_original_video', $moduleConfig);
    }

    /**
     * Test that default enabled types is empty.
     */
    public function testDefaultEnabledTypesIsEmpty(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $this->assertEmpty($config['derivativemedia']['settings']['derivativemedia_enable']);
    }

    /**
     * Test that default max size live is 30 MB.
     */
    public function testDefaultMaxSizeLive(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $this->assertEquals(30, $config['derivativemedia']['settings']['derivativemedia_max_size_live']);
    }
}
