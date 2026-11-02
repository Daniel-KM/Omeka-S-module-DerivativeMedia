<?php declare(strict_types=1);

namespace DerivativeMediaTest;

use CommonTest\AbstractHttpControllerTestCase;

/**
 * Tests for the upgrade script.
 */
class UpgradeTest extends AbstractHttpControllerTestCase
{
    use DerivativeMediaTestTrait;

    const OLD_WEBM = '-c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus';

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
     * Run the real upgrade from 3.4.16 with the video converters.
     */
    protected function upgradeFrom3416(?array $converters): array
    {
        $services = $this->getApplicationServiceLocator();
        $settings = $services->get('Omeka\Settings');
        if ($converters !== null) {
            $settings->set('derivativemedia_converters_video', $converters);
        }
        $module = $services->get('ModuleManager')->getModule('DerivativeMedia');
        $module->setServiceLocator($services);
        $module->upgrade('3.4.16', '3.4.17', $services);
        return $settings->get('derivativemedia_converters_video');
    }

    protected function newWebm(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/module.config.php';
        return $config['derivativemedia']['settings']['derivativemedia_converters_video']['webm/{filename}.webm'];
    }

    public function testDefaultWebmConverterIsUpdated(): void
    {
        $converters = $this->upgradeFrom3416([
            '# Comment.' => '',
            'webm/{filename}.webm' => self::OLD_WEBM,
            'mp4/{filename}.mp4' => '-c copy -c:v libx264',
        ]);
        $this->assertSame($this->newWebm(), $converters['webm/{filename}.webm']);
        $this->assertStringContainsString('-row-mt 1', $converters['webm/{filename}.webm']);
        // The order and the other converters are kept.
        $this->assertSame(['# Comment.', 'webm/{filename}.webm', 'mp4/{filename}.mp4'], array_keys($converters));
        $this->assertSame('-c copy -c:v libx264', $converters['mp4/{filename}.mp4']);

        // A replayed upgrade does not change it again.
        $this->assertSame($converters, $this->upgradeFrom3416(null));
    }

    public function testModifiedWebmConverterIsKept(): void
    {
        $modified = self::OLD_WEBM . ' -cpu-used 4';
        $converters = $this->upgradeFrom3416([
            'webm/{filename}.webm' => $modified,
        ]);
        $this->assertSame($modified, $converters['webm/{filename}.webm']);
    }
}
