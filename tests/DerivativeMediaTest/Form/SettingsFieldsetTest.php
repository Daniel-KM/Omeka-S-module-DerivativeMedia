<?php declare(strict_types=1);

namespace DerivativeMediaTest\Form;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMedia\Form\SettingsFieldset;
use DerivativeMediaTest\DerivativeMediaTestTrait;

/**
 * Tests for the SettingsFieldset form.
 */
class SettingsFieldsetTest extends AbstractHttpControllerTestCase
{
    use DerivativeMediaTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test that the settings fieldset can be instantiated.
     */
    public function testFieldsetCanBeCreated(): void
    {
        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $fieldset = $formManager->get(SettingsFieldset::class);
        $this->assertInstanceOf(SettingsFieldset::class, $fieldset);
    }

    /**
     * Test that fieldset contains expected elements.
     */
    public function testFieldsetContainsExpectedElements(): void
    {
        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $fieldset = $formManager->get(SettingsFieldset::class);

        $expectedElements = [
            'derivativemedia_enable',
            'derivativemedia_update',
            'derivativemedia_max_size_live',
            'derivativemedia_converters_audio',
            'derivativemedia_converters_video',
            'derivativemedia_converters_pdf',
            'derivativemedia_append_original_audio',
            'derivativemedia_append_original_video',
        ];

        foreach ($expectedElements as $name) {
            $this->assertTrue(
                $fieldset->has($name),
                "Fieldset should have element '$name'"
            );
        }
    }

    /**
     * Test that enable element has all derivative type options.
     */
    public function testEnableElementHasAllTypeOptions(): void
    {
        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $fieldset = $formManager->get(SettingsFieldset::class);

        $enableElement = $fieldset->get('derivativemedia_enable');
        $options = $enableElement->getValueOptions();

        $expectedOptions = [
            'audio', 'video', 'pdf_media', 'zip', 'zipm', 'zipo',
            'pdf', 'txt', 'text', 'alto', 'iiif-2', 'iiif-3', 'pdf2xml',
        ];

        foreach ($expectedOptions as $optionKey) {
            $this->assertArrayHasKey($optionKey, $options, "Enable element should have option '$optionKey'");
        }
    }

    /**
     * Test that update element has all update mode options.
     */
    public function testUpdateElementHasAllModeOptions(): void
    {
        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $fieldset = $formManager->get(SettingsFieldset::class);

        $updateElement = $fieldset->get('derivativemedia_update');
        $options = $updateElement->getValueOptions();

        $expectedModes = ['no', 'existing_live', 'existing', 'all_live', 'all'];

        foreach ($expectedModes as $mode) {
            $this->assertArrayHasKey($mode, $options, "Update element should have mode '$mode'");
        }
    }

    /**
     * Test that fieldset has correct id attribute.
     */
    public function testFieldsetHasCorrectIdAttribute(): void
    {
        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $fieldset = $formManager->get(SettingsFieldset::class);
        $this->assertEquals('derivative-media', $fieldset->getAttribute('id'));
    }
}
