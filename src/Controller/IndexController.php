<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;

class IndexController extends \Omeka\Controller\IndexController
{
    use TraitDerivative;

    /**
     * @todo Manage other storage type. See module Access.
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
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('This type is not supported.'), // @translate
            ]);
        }

        $derivativeEnabled = $this->settings()->get('derivativemedia_enable', []);
        if (!in_array($type, $derivativeEnabled)) {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('This type is not available.'), // @translate
            ]);
        }

        $id = $this->params('id');

        // Check if the resource is available and rights for the current user.

        // Automatically throw exception.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource*/
        $resource = $this->api()->read('resources', ['id' => $id])->getContent();

        // Check if resource contains files.
        if ($resource->resourceName() !== 'items') {
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('Resource is not an item.'), // @translate
            ]);
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
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
            return new JsonModel([
                'status' => 'fail',
                'data' => [
                    'id' => $this->translate('This derivative is ready. Reload the page.'), // @translate
                ],
            ]);
        }

        if (!$ready) {
            if (Module::DERIVATIVES[$type]['mode'] === 'static') {
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate('This derivative is not ready. Ask the webmaster for it.'), // @translate
                ]);
            }

            $dataMedia = $this->dataMedia($item, $type);
            if (!$dataMedia) {
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate('This type of derivative file cannot be prepared for this item.'), // @translate
                ]);
            }

            if (!$prepare
                && (
                    Module::DERIVATIVES[$type]['mode'] === 'live'
                    || (Module::DERIVATIVES[$type]['mode'] === 'dynamic_live'
                        && !empty(Module::DERIVATIVES[$type]['size'])
                        && array_sum(array_column($dataMedia, 'size')) < (int) $this->settings()->get('derivativemedia_max_size_live', 30) * 1048576
                    )
                )
            ) {
                $ready = $this->createDerivative($type, $filepath, $item, $dataMedia);
                if (!$ready) {
                    $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                    return new JsonModel([
                        'status' => 'error',
                        'message' => $this->translate('This derivative files of this item cannot be prepared.'), // @translate
                    ]);
                }
            } else {
                $args = [
                    'item_id' => $item->id(),
                    'type' => $type,
                    'data_media' => $dataMedia,
                ];
                /** @var \Omeka\Job\Dispatcher $dispatcher */
                $dispatcher = $this->jobDispatcher();
                $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivatives::class, $args);
                $this->getResponse()->setStatusCode(Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'fail',
                    'data' => [
                        'id' => $this->translate('This derivative is being created. Come back later.'), // @translate
                    ],
                ]);
            }
        }

        // Send the file.
        return $this->sendFile($filepath, [
            'content_type' => Module::DERIVATIVES[$type]['mediatype'],
            'disposition_mode' => 'attachment',
            'cache' => true,
        ]);
    }
}
