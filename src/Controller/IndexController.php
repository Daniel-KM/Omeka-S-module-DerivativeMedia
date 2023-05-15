<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use Omeka\Api\Representation\ItemRepresentation;
use ZipArchive;

class IndexController extends \Omeka\Controller\IndexController
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @todo Manage other storage type. See module AccessResource.
     * @todo Some formats don't really need storage (text…), so make them truly dynamic.
     *
     * {@inheritDoc}
     * @see \Omeka\Controller\IndexController::indexAction()
     */
    public function indexAction()
    {
        $mediaTypes = [
            'alto' => 'application/alto+xml',
            'text' => 'text/plain',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'zipm' => 'application/zip',
            'zipo' => 'application/zip',
        ];

        $mediaExtensions = [
            'alto' => 'alto.xml',
            'text' => 'txt',
            'txt' => 'txt',
            'zip' => 'zip',
            'zipm' => 'zip',
            'zipo' => 'zip',
        ];

        $type = $this->params('type');
        if (!isset($type, $mediaTypes)) {
            throw new \Omeka\Mvc\Exception\RuntimeException('This type is not supported.'); // @translate
        }

        $derivativeEnabled = $this->settings()->get('derivativemedia_enable', []);
        if (!in_array($type, $derivativeEnabled)) {
            throw new \Omeka\Mvc\Exception\RuntimeException('This type is not available.'); // @translate
        }

        $id = $this->params('id');

        // Check if the resource is available for the current user.
        // Automatically throw exception.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource*/
        $resource = $this->api()->read('resources', ['id' => $id])->getContent();

        // Check if resource contains files.
        if ($resource->resourceName() !== 'items') {
            throw new \Omeka\Mvc\Exception\RuntimeException('Resource is not an item.'); // @translate
        }

        $services = $resource->getServiceLocator();
        $config = $services->get('Config');
        $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $item = $resource;

        // Quick check if the file exists.
        $filepath = $this->basePath . '/' . $type . '/' . $id . '.' . $mediaExtensions[$type];
        $ready = file_exists($filepath) && is_readable($filepath) && filesize($filepath);

        if (!$ready) {
            $mediaData = $this->mediaData($item, $type);
            if (!count($mediaData)) {
                throw new \Omeka\Mvc\Exception\RuntimeException('This item has no media.'); // @translate
            }
            $ready = $this->prepareDerivative($item, $filepath, $mediaData, $type);
            if (!$ready) {
                throw new \Omeka\Mvc\Exception\RuntimeException('This derivative files of this item cannot be prepared.'); // @translate
            }
        }

        // Send the file.
        return $this->sendFile($filepath, $mediaTypes[$type], basename($filepath), 'attachment', true);
    }

    /**
     * This is the 'file' action that is invoked when a user wants to download
     * the given file.
     *
     * @see \AccessResource\Controller\AccessResourceController::sendFile()
     * @see \DerivativeMedia\Controller\IndexController::sendFile()
     * @see \Statistics\Controller\DownloadController::sendFile()
     */
    protected function sendFile(
        string $filepath,
        string $mediaType,
        ?string $filename = null,
        // "inline" or "attachment".
        // It is recommended to set attribute "download" to link tag "<a>".
        ?string $dispositionMode = 'inline',
        ?bool $cache = false
    ): \Laminas\Http\PhpEnvironment\Response {
        $filename = $filename ?: basename($filepath);
        $filesize = (int) filesize($filepath);

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();

        // Write headers.
        $headers = $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $dispositionMode, $filename))
            ->addHeaderLine(sprintf('Content-Length: %s', $filesize))
            ->addHeaderLine('Content-Transfer-Encoding: binary');
        if ($cache) {
            // Use this to open files directly.
            // Cache for 30 days.
            $headers
                ->addHeaderLine('Cache-Control: private, max-age=2592000, post-check=2592000, pre-check=2592000')
                ->addHeaderLine(sprintf('Expires: %s', gmdate('D, d M Y H:i:s', time() + (30 * 24 * 60 * 60)) . ' GMT'));
        }

        // Send headers separately to handle large files.
        $response->sendHeaders();

        // TODO Use Laminas stream response.

        // Clears all active output buffers to avoid memory overflow.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($filepath);

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        return $response;
    }

    protected function mediaData(ItemRepresentation $item, string $type): array
    {
        $mediaData = [];
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
            $mainType = strtok($mediaType, '/');
            $extension = $media->extension();
            if ($type === 'zipm' && !in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'zipo' && in_array($mainType, ['image', 'audio', 'video'])) {
                continue;
            } elseif ($type === 'txt'
                // Manage extracted text without content.
                && ($mediaType !== 'text/plain' || ($extension === 'txt' && !in_array($mediaType, ['application/x-empty', 'text/plain'])))
            ) {
                continue;
            } elseif ($type === 'alto'
                // Manage extracted text without content.
                && ($mediaType !== 'application/alto+xml' || ($extension === 'xml' && !in_array($mediaType, ['application/x-empty', 'application/alto+xml'])))
            ) {
                continue;
            } elseif ($type === 'text'
                // This is an exception.
                && (($extracted = (string) $media->value('extracttext:extracted_text')) && strlen($extracted))
            ) {
                $mediaData[$media->id()] = $extracted;
                continue;
            }
            $mediaData[$media->id()] = [
                'source' => $media->source(),
                'filename' => $filename,
                'filepath' => $filepath,
                'mediatype' => $mediaType,
                'maintype' => $mainType,
                'extension' => $extension,
                'size' => $media->size(),
            ];
        }
        return $mediaData;
    }

    protected function prepareDerivative(ItemRepresentation $item, string $filepath, array $mediaData, string $type): ?bool
    {
        if (!$this->ensureDirectory(dirname($filepath))) {
            $this->logger()->err('Enable to create directory.'); // @translate
            return null;
        }

        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                $this->logger()->err('Enable to remove existing file.'); // @translate
                return null;
            }
        }

        if ($type === 'alto') {
            return $this->prepareDerivativeAlto($item, $filepath, $mediaData);
        } elseif ($type === 'text') {
            return $this->prepareDerivativeTextExtracted($item, $filepath, $mediaData);
        } elseif ($type === 'txt') {
            return $this->prepareDerivativeText($item, $filepath, $mediaData);
        } elseif (substr($type, 0, 3) === 'zip') {
            return $this->prepareDerivativeZip($item, $filepath, $mediaData, $type);
        }
    }

    protected function prepareDerivativeAlto(ItemRepresentation $item, string $filepath, array $mediaData): ?bool
    {
        $helpers = $this->viewHelpers();
        if (!$helpers->has('xmlAltoSingle')) {
            $this->logger()->err('To create xml alto, the module IiifSearch is required for now.'); // @translate
        }
        /** @var \IiifSearch\View\Helper\XmlAltoSingle $xmlAltoSingle */
        $xmlAltoSingle = $helpers->get('xmlAltoSingle');
        $result = $xmlAltoSingle($item, $filepath, $mediaData);
        return (bool) $result;
    }

    protected function prepareDerivativeTextExtracted(ItemRepresentation $item, string $filepath, array $mediaData): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($mediaData);
        $index = 0;
        foreach ($mediaData as $mediaData) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= $mediaData . PHP_EOL;
        }

        $result = file_put_contents($filepath, trim($output));

        return (bool) $result;
    }

    protected function prepareDerivativeText(ItemRepresentation $item, string $filepath, array $mediaData): ?bool
    {
        $output = '';

        $pageSeparator = <<<'TXT'
==============
Page %1$d/%2$d
==============


TXT;

        $total = count($mediaData);
        $index = 0;
        foreach ($mediaData as $mediaData) {
            ++$index;
            $output .= sprintf($pageSeparator, $index, $total);
            $output .= file_get_contents($mediaData['filepath']) . PHP_EOL;
        }

        $result = file_put_contents($filepath, trim($output));

        return (bool) $result;
    }

    protected function prepareDerivativeZip(ItemRepresentation $item, string $filepath, array $mediaData, string $type): ?bool
    {
        if (!class_exists('ZipArchive')) {
            $this->logger()->err('The php extension "php-zip" must be installed.'); // @translate
            return null;
        }

        // ZipArchive::OVERWRITE is available only in php 8.
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
            $this->logger()->err('Unable to create the zip file.'); // @translate
            return null;
        }

        // Here, the site may not be available, so can't store item site url.
        $comment = $this->settings()->get('installation_title') . ' [' . $this->url()->fromRoute('top', [], ['force_canonical' => true]) . ']';
        $zip->setArchiveComment($comment);

        // Store files: they are all already compressed (image, video, pdf...),
        // except some txt, xml and old docs.

        $index = 0;
        foreach ($mediaData as $file) {
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
}
