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
     *
     * {@inheritDoc}
     * @see \Omeka\Controller\IndexController::indexAction()
     */
    public function indexAction()
    {
        $mediaTypes = [
            'zip' => 'application/zip',
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
        $filepath = $this->basePath . '/' . $type . '/' . $id . '.' . $type;
        $ready = file_exists($filepath) && is_readable($filepath) && filesize($filepath);

        if (!$ready) {
            $mediaData = $this->mediaData($item);
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

    protected function mediaData(ItemRepresentation $item): array
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
            $mediaData[$media->id()] = [
                'source' => $media->source(),
                'filename' => $filename,
                'filepath' => $filepath,
                'mediatype' => $mediaType,
                'maintype' => strtok($mediaType, '/'),
                'extension' => $media->extension(),
                'size' => $media->size(),
            ];
        }
        return $mediaData;
    }

    protected function prepareDerivative(ItemRepresentation $item, string $filepath, array $mediaData, string $type): ?bool
    {
        // TODO Type is always zip for now.
        if (!class_exists('ZipArchive')) {
            $this->logger()->err('The php extension "php-zip" must be installed.'); // @translate
            return null;
        }

        if (!$this->ensureDirectory(dirname($filepath))) {
            $this->logger()->err('Enable to create directory.'); // @translate
            return null;
        }

        // ZipArchive::OVERWRITE is available only in php 8.
        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                $this->logger()->err('Enable to remove existing file.'); // @translate
                return null;
            }
        }

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
