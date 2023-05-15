<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;

class IndexController extends \Omeka\Controller\IndexController
{
    use TraitDerivative;

    /**
     * @todo Manage other storage type. See module AccessResource.
     * @todo Some formats don't really need storage (text…), so make them truly dynamic.
     *
     * @todo Dynamic files cannot be stored in media data because of rights.
     *
     * {@inheritDoc}
     * @see \Omeka\Controller\IndexController::indexAction()
     */
    public function indexAction()
    {
        $type = $this->params('type');
        if (!isset(Module::DERIVATIVES[$type])
            || Module::DERIVATIVES[$type]['level'] === 'media'
        ) {
            throw new \Omeka\Mvc\Exception\RuntimeException('This type is not supported.'); // @translate
        }

        $derivativeEnabled = $this->settings()->get('derivativemedia_enable', []);
        if (!in_array($type, $derivativeEnabled)) {
            throw new \Omeka\Mvc\Exception\RuntimeException('This type is not available.'); // @translate
        }

        $id = $this->params('id');

        // Check if the resource is available and rights for the current user.

        // Automatically throw exception.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource*/
        $resource = $this->api()->read('resources', ['id' => $id])->getContent();

        // Check if resource contains files.
        if ($resource->resourceName() !== 'items') {
            throw new \Omeka\Mvc\Exception\RuntimeException('Resource is not an item.'); // @translate
        }

        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $item = $resource;

        $force = !empty($this->params()->fromQuery('force'));
        $prepare = !empty($this->params()->fromQuery('prepare'));

        // Quick check if the file exists when needed.
        $filepath = $this->itemFilepath($item, $type);

        $ready = !$force
            && file_exists($filepath) && is_readable($filepath) && filesize($filepath);

        // In case a user reclicks the link.
        if ($prepare && $ready) {
            throw new \Omeka\Mvc\Exception\RuntimeException('This derivative is ready. Reload the page.'); // @translate
        }

        if (!$ready) {
            if (Module::DERIVATIVES[$type]['mode'] === 'static') {
                throw new \Omeka\Mvc\Exception\RuntimeException('This derivative is not ready. Ask the webmaster for it.'); // @translate
            }

            $dataMedia = $this->dataMedia($item, $type);
            if (!$dataMedia) {
                throw new \Omeka\Mvc\Exception\RuntimeException('This type of derivative file cannot be prepared for this item.'); // @translate
            }

            if (!$prepare && Module::DERIVATIVES[$type]['mode'] === 'live') {
                $ready = $this->createDerivative($type, $filepath, $item, $dataMedia);
                if (!$ready) {
                    throw new \Omeka\Mvc\Exception\RuntimeException('This derivative files of this item cannot be prepared.'); // @translate
                }
            } else {
                $args = [
                    'itemId' => $item->id(),
                    'type' => $type,
                    'dataMedia' => $dataMedia,
                ];
                /** @var \Omeka\Job\Dispatcher $dispatcher */
                $dispatcher = $this->jobDispatcher();
                $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivative::class, $args);
                throw new \Omeka\Mvc\Exception\RuntimeException('This derivative is being created. Come back later.'); // @translate
            }
        }

        // Send the file.
        return $this->sendFile($filepath, Module::DERIVATIVES[$type]['mediatype'], basename($filepath), 'attachment', true);
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
}
