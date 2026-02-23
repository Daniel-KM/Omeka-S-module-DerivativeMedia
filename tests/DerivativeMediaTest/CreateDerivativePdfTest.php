<?php declare(strict_types=1);

namespace DerivativeMediaTest;

use CommonTest\AbstractHttpControllerTestCase;
use DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative;

/**
 * Integration tests for the PDF builder in CreateDerivative.
 *
 * Creates real fixture files (JPEG, PDF) in setUp and cleans up in
 * tearDown. Tests the protected helper methods via reflection.
 *
 * Requires: gs (ghostscript), pdfinfo, pdftotext, convert (ImageMagick).
 */
class CreateDerivativePdfTest extends AbstractHttpControllerTestCase
{
    use DerivativeMediaTestTrait;

    protected ?string $tempDir = null;

    protected ?CreateDerivative $plugin = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $this->tempDir = sys_get_temp_dir()
            . '/derivative_media_test_' . getmypid();
        @mkdir($this->tempDir, 0755, true);

        $this->createFixtures();
    }

    public function tearDown(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            @rmdir($this->tempDir);
        }
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Create test fixture files.
     */
    protected function createFixtures(): void
    {
        // Two small JPEG images.
        foreach (['test1.jpg', 'test2.jpg'] as $name) {
            $img = imagecreatetruecolor(10, 10);
            imagejpeg($img, $this->tempDir . '/' . $name, 75);
            imagedestroy($img);
        }

        // PDF without text (from image).
        shell_exec(sprintf(
            'convert %s %s 2>/dev/null',
            escapeshellarg($this->tempDir . '/test1.jpg'),
            escapeshellarg($this->tempDir . '/notext.pdf')
        ));

        // Second single-page PDF without text.
        shell_exec(sprintf(
            'convert %s %s 2>/dev/null',
            escapeshellarg($this->tempDir . '/test2.jpg'),
            escapeshellarg($this->tempDir . '/notext2.pdf')
        ));

        // PDF with text layer (via PostScript).
        $text = str_repeat(
            'This is test content for text layer detection. ',
            5
        );
        $ps = "%!PS\n"
            . "/Helvetica findfont 12 scalefont setfont\n"
            . "72 700 moveto\n"
            . '(' . addcslashes($text, '()\\') . ") show\n"
            . "showpage\n";
        file_put_contents($this->tempDir . '/text.ps', $ps);
        shell_exec(sprintf(
            'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite'
            . ' -sOutputFile=%s %s 2>/dev/null',
            escapeshellarg($this->tempDir . '/withtext.pdf'),
            escapeshellarg($this->tempDir . '/text.ps')
        ));

        // Multi-page PDF (2 pages, from 2 images).
        shell_exec(sprintf(
            'convert %s %s %s 2>/dev/null',
            escapeshellarg($this->tempDir . '/test1.jpg'),
            escapeshellarg($this->tempDir . '/test2.jpg'),
            escapeshellarg($this->tempDir . '/multipage.pdf')
        ));
    }

    /**
     * Get the CreateDerivative controller plugin.
     */
    protected function getPlugin(): CreateDerivative
    {
        if (!$this->plugin) {
            $services = $this->getServiceLocator();
            $plugins = $services->get('ControllerPluginManager');
            $this->plugin = $plugins->get('createDerivative');
        }
        return $this->plugin;
    }

    /**
     * Call a protected method on the plugin.
     *
     * @return mixed
     */
    protected function callMethod(string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($this->getPlugin(), $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->getPlugin(), $args);
    }

    /**
     * Skip if a required tool is missing.
     */
    protected function requireTool(string $tool): void
    {
        if (shell_exec("hash $tool 2>&- || echo 1")) {
            $this->markTestSkipped("Requires $tool.");
        }
    }

    /**
     * Skip if a fixture file was not created.
     */
    protected function requireFixture(string $filename): void
    {
        $path = $this->tempDir . '/' . $filename;
        if (!file_exists($path) || !filesize($path)) {
            $this->markTestSkipped("Fixture $filename not created.");
        }
    }

    // ------------------------------------------------------------------
    // pdfPageCount
    // ------------------------------------------------------------------

    public function testPdfPageCountSinglePage(): void
    {
        $this->requireTool('pdfinfo');
        $this->requireFixture('notext.pdf');
        $count = $this->callMethod(
            'pdfPageCount',
            [$this->tempDir . '/notext.pdf']
        );
        $this->assertSame(1, $count);
    }

    public function testPdfPageCountMultiPage(): void
    {
        $this->requireTool('pdfinfo');
        $this->requireFixture('multipage.pdf');
        $count = $this->callMethod(
            'pdfPageCount',
            [$this->tempDir . '/multipage.pdf']
        );
        $this->assertSame(2, $count);
    }

    public function testPdfPageCountNonExistentFile(): void
    {
        $count = $this->callMethod(
            'pdfPageCount',
            ['/nonexistent/file.pdf']
        );
        $this->assertSame(0, $count);
    }

    // ------------------------------------------------------------------
    // pdfPageCounts
    // ------------------------------------------------------------------

    public function testPdfPageCountsBatch(): void
    {
        $this->requireTool('pdfinfo');
        $this->requireFixture('notext.pdf');
        $this->requireFixture('multipage.pdf');

        $pdfs = [
            ['filepath' => $this->tempDir . '/notext.pdf'],
            ['filepath' => $this->tempDir . '/multipage.pdf'],
        ];
        $counts = $this->callMethod('pdfPageCounts', [$pdfs]);
        $this->assertSame([1, 2], $counts);
    }

    // ------------------------------------------------------------------
    // pdfHasTextLayer
    // ------------------------------------------------------------------

    public function testPdfHasTextLayerFalseForImagePdf(): void
    {
        $this->requireTool('pdftotext');
        $this->requireTool('pdfinfo');
        $this->requireFixture('notext.pdf');

        $hasText = $this->callMethod(
            'pdfHasTextLayer',
            [$this->tempDir . '/notext.pdf']
        );
        $this->assertFalse($hasText);
    }

    public function testPdfHasTextLayerTrueForTextPdf(): void
    {
        $this->requireTool('pdftotext');
        $this->requireTool('pdfinfo');
        $this->requireFixture('withtext.pdf');

        $hasText = $this->callMethod(
            'pdfHasTextLayer',
            [$this->tempDir . '/withtext.pdf']
        );
        $this->assertTrue($hasText);
    }

    // ------------------------------------------------------------------
    // prepareDerivativePdf — strategy selection
    // ------------------------------------------------------------------

    public function testPdfStrategyImagesOnly(): void
    {
        $this->requireTool('convert');
        $output = $this->tempDir . '/out_images.pdf';
        $dataMedia = [
            $this->makeDataMedia(1, 'test1.jpg', 'image/jpeg', 'image'),
            $this->makeDataMedia(2, 'test2.jpg', 'image/jpeg', 'image'),
        ];
        $result = $this->callMethod(
            'prepareDerivativePdf',
            [$output, $dataMedia, null]
        );
        $this->assertTrue($result);
        $this->assertFileExists($output);
        // Should produce a 2-page PDF.
        $pages = $this->callMethod('pdfPageCount', [$output]);
        $this->assertSame(2, $pages);
    }

    public function testPdfStrategySinglePdfCopies(): void
    {
        $this->requireFixture('notext.pdf');
        $output = $this->tempDir . '/out_copy.pdf';
        $dataMedia = [
            $this->makeDataMedia(
                1, 'notext.pdf', 'application/pdf', 'application'
            ),
        ];
        $result = $this->callMethod(
            'prepareDerivativePdf',
            [$output, $dataMedia, null]
        );
        $this->assertTrue($result);
        $this->assertFileExists($output);
        // Should be same size as original (copy).
        $this->assertSame(
            filesize($this->tempDir . '/notext.pdf'),
            filesize($output)
        );
    }

    public function testPdfStrategyMultiplePdfsMerge(): void
    {
        $this->requireTool('gs');
        $this->requireFixture('notext.pdf');
        $this->requireFixture('notext2.pdf');
        $output = $this->tempDir . '/out_merge.pdf';
        $dataMedia = [
            $this->makeDataMedia(
                1, 'notext.pdf', 'application/pdf', 'application'
            ),
            $this->makeDataMedia(
                2, 'notext2.pdf', 'application/pdf', 'application'
            ),
        ];
        $result = $this->callMethod(
            'prepareDerivativePdf',
            [$output, $dataMedia, null]
        );
        $this->assertTrue($result);
        $this->assertFileExists($output);
        $pages = $this->callMethod('pdfPageCount', [$output]);
        $this->assertSame(2, $pages);
    }

    public function testPdfStrategyDetectsGlobalPdf(): void
    {
        $this->requireTool('convert');
        $this->requireTool('pdfinfo');
        $this->requireFixture('notext.pdf');
        $this->requireFixture('notext2.pdf');
        $this->requireFixture('multipage.pdf');

        $output = $this->tempDir . '/out_global.pdf';
        $dataMedia = [
            // 2 images.
            $this->makeDataMedia(1, 'test1.jpg', 'image/jpeg', 'image'),
            $this->makeDataMedia(2, 'test2.jpg', 'image/jpeg', 'image'),
            // 2 single-page PDFs.
            $this->makeDataMedia(
                3, 'notext.pdf', 'application/pdf', 'application'
            ),
            $this->makeDataMedia(
                4, 'notext2.pdf', 'application/pdf', 'application'
            ),
            // 1 multi-page PDF (the "global" document).
            $this->makeDataMedia(
                5, 'multipage.pdf', 'application/pdf', 'application'
            ),
        ];
        $result = $this->callMethod(
            'prepareDerivativePdf',
            [$output, $dataMedia, null]
        );
        $this->assertTrue($result);
        // Strategy should use images (2 pages), not merge PDFs (4 pages).
        $pages = $this->callMethod('pdfPageCount', [$output]);
        $this->assertSame(2, $pages);
    }

    public function testPdfStrategyEmptyDataReturnsFalse(): void
    {
        $output = $this->tempDir . '/out_empty.pdf';
        $result = $this->callMethod(
            'prepareDerivativePdf',
            [$output, [], null]
        );
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // ocrPdfIfNeeded — graceful skip when ocrmypdf is absent
    // ------------------------------------------------------------------

    public function testOcrSkippedWhenOcrmypdfMissing(): void
    {
        $this->requireFixture('notext.pdf');

        // Copy fixture to test file.
        $testPdf = $this->tempDir . '/ocr_test.pdf';
        copy($this->tempDir . '/notext.pdf', $testPdf);
        $sizeBefore = filesize($testPdf);

        // If ocrmypdf is not installed, the file should remain unchanged.
        $hasOcrmypdf = !shell_exec('hash ocrmypdf 2>&- || echo 1');
        $this->callMethod('ocrPdfIfNeeded', [$testPdf]);

        if (!$hasOcrmypdf) {
            $this->assertSame($sizeBefore, filesize($testPdf));
        } else {
            // If installed, OCR was run (file may change size).
            $this->assertFileExists($testPdf);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a dataMedia array entry.
     */
    protected function makeDataMedia(
        int $id,
        string $filename,
        string $mediatype,
        string $maintype
    ): array {
        $filepath = $this->tempDir . '/' . $filename;
        return [
            'id' => $id,
            'source' => $filename,
            'filename' => $filename,
            'filepath' => $filepath,
            'mediatype' => $mediatype,
            'maintype' => $maintype,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'size' => file_exists($filepath) ? filesize($filepath) : 0,
        ];
    }
}
