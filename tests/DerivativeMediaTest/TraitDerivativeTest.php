<?php declare(strict_types=1);

namespace DerivativeMediaTest;

use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for TraitDerivative path logic.
 *
 * Uses an anonymous class to expose the protected trait methods.
 */
class TraitDerivativeTest extends TestCase
{
    protected object $trait;

    protected function setUp(): void
    {
        $this->trait = new class {
            use \DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;

            public function publicTempFilepath(string $filepath): string
            {
                return $this->tempFilepath($filepath);
            }

            public function setBasePath(string $path): void
            {
                $this->basePath = $path;
            }
        };
        $this->trait->setBasePath('/var/www/html/files');
    }

    public function testTempFilepathWithExtension(): void
    {
        $result = $this->trait->publicTempFilepath('/path/to/file.pdf');
        $this->assertSame('/path/to/file.tmp.pdf', $result);
    }

    public function testTempFilepathWithDoubleExtension(): void
    {
        $result = $this->trait->publicTempFilepath('/path/to/123.alto.xml');
        $this->assertSame('/path/to/123.alto.tmp.xml', $result);
    }

    public function testTempFilepathWithoutExtension(): void
    {
        $result = $this->trait->publicTempFilepath('/path/to/file');
        $this->assertSame('/path/to/file.tmp', $result);
    }

    public function testTempFilepathPreservesDirectory(): void
    {
        $result = $this->trait->publicTempFilepath('/var/www/files/pdf/42.pdf');
        $this->assertSame('/var/www/files/pdf/42.tmp.pdf', $result);
    }

    public function testTempFilepathWithManifestJson(): void
    {
        $result = $this->trait->publicTempFilepath('/files/iiif/3/42.manifest.json');
        $this->assertSame('/files/iiif/3/42.manifest.tmp.json', $result);
    }
}
