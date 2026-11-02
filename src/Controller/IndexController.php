<?php declare(strict_types=1);

namespace DerivativeMedia\Controller;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Representation\ItemRepresentation;

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
            && file_exists($filepath) && is_readable($filepath) && filesize($filepath)
            && $this->isUpToDate($item, $filepath);

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
                // Each click would start a new job for the same file: a big
                // item could then saturate the server.
                if (!$this->isDerivativePending($item, $type)) {
                    $args = [
                        'item_id' => $item->id(),
                        'type' => $type,
                        'data_media' => $dataMedia,
                    ];
                    /** @var \Omeka\Job\Dispatcher $dispatcher */
                    $dispatcher = $this->jobDispatcher();
                    $dispatcher->dispatch(\DerivativeMedia\Job\CreateDerivatives::class, $args);
                }
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

    /**
     * Check if a recent job is already creating this derivative for the item.
     *
     * A job stuck since more than one day is ignored, so a crashed job does not
     * block the derivative forever.
     */
    protected function isDerivativePending(ItemRepresentation $item, string $type): bool
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $item->getServiceLocator()->get('Omeka\Connection');
        $sql = <<<'SQL'
            SELECT `id`
            FROM `job`
            WHERE `class` = :class
                AND `status` IN (:statuses)
                AND `started` > :since
                AND JSON_EXTRACT(`args`, '$.item_id') = :item_id
                AND JSON_UNQUOTE(JSON_EXTRACT(`args`, '$.type')) = :type
            LIMIT 1
            SQL;
        return (bool) $connection->fetchOne($sql, [
            'class' => \DerivativeMedia\Job\CreateDerivatives::class,
            'statuses' => [\Omeka\Entity\Job::STATUS_STARTING, \Omeka\Entity\Job::STATUS_IN_PROGRESS],
            'since' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
            'item_id' => $item->id(),
            'type' => $type,
        ], [
            'statuses' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
            'item_id' => \PDO::PARAM_INT,
        ]);
    }
}
