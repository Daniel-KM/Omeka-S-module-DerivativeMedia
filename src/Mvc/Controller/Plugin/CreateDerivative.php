<?php declare(strict_types=1);

namespace DerivativeMedia\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Stdlib\Cli;
use ZipArchive;

class CreateDerivative extends AbstractPlugin
{
    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(Cli $cli, string $basePath)
    {
        $this->cli = $cli;
        $this->basePath = $basePath;
    }

    /**
     * Create derivative of an item at the specified filepath.
     *
     * Unlike media, item as no field in database to store data. So the check is
     * done directly on files.
     *
     * @var array $dataMedia Media data contains required values.
     * @return bool|null Success or error. Null if no media or currently being
     * created.
     *
     * @todo Check filepath.
     */
    public function __invoke(string $type, string $filepath, ?ItemRepresentation $item = null, ?array $dataMedia = null): ?bool
    {
        if (!$item && !$dataMedia) {
            return false;
        }

        $dataMedia = $dataMedia ?: $this->dataMedia($type, $item);
        if (empty($dataMedia)) {
            return null;
        }

        return $this->prepareDerivative($type, $filepath, $dataMedia, $item);
    }

    protected function dataMedia(string $type, ItemRepresentation $item): array
    {
        $dataMedia = [];
        foreach ($item->media() as $media) {
            if (!$media->hasOriginal() || !$media->size()) {
                continue;
            }
            $filename = $media->filename();
            $filepath = $this->basePath . '/original/' . $filename;
            $ready = file_exists($filepath) && is_readable($filepath) && filesize($filepath);
            if (!$ready) {
                continue;
            }
            $mediaType = $media->mediaType();
            if (!$mediaType) {
                continue;
            }
            $mediaId = $media->id();
            $mainType = strtok($mediaType, '/');
            $extension = $media->extension();
            if ($type === 'alto'
                // Manage altowithout content.
                && ($mediaType !== 'application/alto+xml' || ($extension === 'xml' && !in_array($mediaType, ['application/x-empty', 'application/alto+xml'])))
            ) {
                continue;
            } elseif ($type === 'pdf'
                && ($mainType !== 'image')
                // TODO Get image and pdf to manage the case there are pdf too.
                // && ($mainType !== 'image' || $mediaType !== 'application/pdf')
            ) {
                continue;
            } elseif ($type === 'txt'
                // Manage extracted text without content.
                && ($mediaType !== 'text/plain' || ($extension === 'txt' && !in_array($mediaType, ['application/x-empty', 'text/plain'])))
            ) {
                continue;
            } elseif ($type === 'text'
                // This is an exception.
                && (($extracted = (string) $media->value('extracttext:extracted_text')) && strlen($extracted))
            ) {
                $dataMedia[$mediaId] = [
                    'id' => $mediaId,
                    'content' => $extracted,
                ];
                continue;
            } elseif ($type === 'zipm' && !in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'zipo' && in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            }
            $dataMedia[$mediaId] = [
                'id' => $mediaId,
                'source' => $media->source(),
                'filename' => $filename,
                'filepath' => $filepath,
                'mediatype' => $mediaType,
                'maintype' => $mainType,
                'extension' => $extension,
                'size' => $media->size(),
            ];
        }
        return $dataMedia;
    }

    protected function prepareDerivative(string $type, string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (!$this->ensureDirectory(dirname($filepath))) {
            $this->logger()->err('Unable to create directory.'); // @translate
            return false;
        }

        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                $this->logger()->err('Unable to remove existing file.'); // @translate
                return false;
            }
        }

        // Use a temp file to avoid concurrent processes (two users request it).
        $tempFilepath = $this->tempFilepath($filepath);

        // Check if another process is creating the file.
        if (file_exists($tempFilepath)) {
            $this->logger()->warn('The derivative is currently beeing created.'); // @translate
            return null;
        }

        if ($type === 'alto') {
            $result = $this->prepareDerivativeAlto($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'pdf') {
            $result = $this->prepareDerivativePdf($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'text') {
            $result = $this->prepareDerivativeTextExtracted($tempFilepath, $dataMedia, $item);
        } elseif ($type === 'txt') {
            $result = $this->prepareDerivativeText($tempFilepath, $dataMedia, $item);
        } elseif (in_array($type, ['zip', 'zipm', 'zipo'])) {
            $result = $this->prepareDerivativeZip($type, $tempFilepath, $dataMedia, $item);
        }

        if ($result) {
            rename($tempFilepath, $filepath);
            @chmod($filepath, 0664);
        } elseif (file_exists($tempFilepath)) {
            @unlink($tempFilepath);
        }

        return $result;
    }

    protected function prepareDerivativeAlto(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $helpers = $this->viewHelpers();
        if (!$helpers->has('xmlAltoSingle')) {
            $this->logger()->err('To create xml alto, the module IiifSearch is required for now.'); // @translate
            return false;
        }
        /** @var \IiifSearch\View\Helper\XmlAltoSingle $xmlAltoSingle */
        $xmlAltoSingle = $helpers->get('xmlAltoSingle');
        $result = $xmlAltoSingle($item, $filepath, $dataMedia);
        return (bool) $result;
    }

    protected function prepareDerivativePdf(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $files = array_column($dataMedia, 'filepath');

        // Avoid to modify quality to speed process.
        $command = 'convert ' . implode(' ', array_map('escapeshellarg', $files)) . ' -quality 100 ' . escapeshellarg($filepath);
        $result = $this->cli->execute($command);

        return $result !== false;
    }

    protected function prepareDerivativeTextExtracted(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($dataMedia);
        $index = 0;
        foreach ($dataMedia as $dataMedia) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= $dataMedia['content'] . PHP_EOL;
        }

        // Fix for windows: remove end of line then add them to fix all cases.
        $output = str_replace(["\r\n", "\n\r","\n"], ["\n", "\n", "\r\n"], trim($output));

        $result = file_put_contents($filepath, $output);

        return (bool) $result;
    }

    protected function prepareDerivativeText(string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($dataMedia);
        $index = 0;
        foreach ($dataMedia as $dataMedia) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= file_get_contents($dataMedia['filepath']) . PHP_EOL;
        }

        // Fix for windows: remove end of line then add them to fix all cases.
        $output = str_replace(["\r\n", "\n\r","\n"], ["\n", "\n", "\r\n"], trim($output));

        $result = file_put_contents($filepath, trim($output));

        return (bool) $result;
    }

    /**
     * @see \ContactUs\Job\ZipResources
     * @see \DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative
     */
    protected function prepareDerivativeZip(string $type, string $filepath, array $dataMedia, ?ItemRepresentation $item): ?bool
    {
        if (!class_exists('ZipArchive')) {
            $this->logger()->err('The php extension "php-zip" must be installed.'); // @translate
            return false;
        }

        // ZipArchive::OVERWRITE is available only in php 8.
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
            $this->logger()->err('Unable to create the zip file.'); // @translate
            return false;
        }

        // Here, the site may not be available, so can't store item site url.
        $comment = $this->settings()->get('installation_title') . ' [' . $this->url()->fromRoute('top', [], ['force_canonical' => true]) . ']';
        $zip->setArchiveComment($comment);

        // Store files: they are all already compressed (image, video, pdf...),
        // except some txt, xml and old docs.

        $index = 0;
        $filenames = [];
        foreach ($dataMedia as $file) {
            $zip->addFile($file['filepath']);
            // Light and quick compress text and xml.
            if ($file['maintype'] === 'text'
                || $file['mediatype'] === 'application/xml'
                || substr($file['mediatype'], -4) === '+xml'
            ) {
                $zip->setCompressionIndex($index, ZipArchive::CM_DEFLATE, 1);
            } else {
                $zip->setCompressionIndex($index, ZipArchive::CM_STORE);
            }

            // Use the source name, but check and rename for unique filename,
            // taking care of extension.
            $basepath = pathinfo($file['source'], PATHINFO_FILENAME);
            $extension = pathinfo($file['source'], PATHINFO_EXTENSION);
            $i = 0;
            do {
                $sourceBase = $basepath . ($i ? '.' . $i : '') . (strlen($extension) ? '.' . $extension : '');
                ++$i;
            } while (in_array($sourceBase, $filenames));
            $filenames[] = $sourceBase;
            $zip->renameName($file['filepath'], $sourceBase);
            ++$index;
        }

        $result = $zip->close();

        return $result;
    }

    protected function ensureDirectory(string $dirpath): bool
    {
        if (file_exists($dirpath)) {
            return true;
        }
        return mkdir($dirpath, 0775, true);
    }

    protected function tempFilepath(string $filepath): string
    {
        // Keep the original extension to manage tools like convert.
        // Normally, all files have an extension.

        $extension = pathinfo($filepath, PATHINFO_EXTENSION) ?? '';
        return strlen($extension)
            ? mb_substr($filepath, 0, - strlen($extension) - 1) . '.tmp' . '.' . $extension
            : $filepath . '.tmp';
    }
}
