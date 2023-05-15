<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class CreateDerivative extends AbstractJob
{
    use TraitDerivative;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/item/job_' . $this->job->getId());

        /** @var \Omeka\Api\Manager $api */
        $api = $services->get('Omeka\ApiManager');

        $itemId = $this->getArg('itemId');
        try {
            /** @var \Omeka\Api\Representation\ItemRepresentation $item */
            $item = $api->read('items', ['id' => $itemId], [], ['initialize' => false])->getContent();
        } catch (NotFoundException $e) {
            $this->logger->err(
                'No item #{item_id}: no derivative media to create.', // @translate
                ['item_id' => $itemId]
            );
            return;
        }

        $type = $this->getArg('type');
        if (!isset(Module::DERIVATIVES[$type])) {
            $this->logger->err(
                'The type {type} is not managed.', // @translate
                ['type' => $type]
            );
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $enabled = $settings->get('derivativemedia_enable', []);
        if (!in_array($type, $enabled)) {
            $this->logger->err(
                'The type {type} is not enabled.', // @translate
                ['type' => $type]
            );
            return;
        }

        $dataMedia = $this->getArg('dataMedia', []);

        $filepath = $this->itemFilepath($item, $type);

        $plugins = $services->get('ControllerPluginManager');
        $createDerivative = $plugins->get('createDerivative');

        // Messages are already logged, except in case of success
        $result = $createDerivative($type, $filepath, $item, $dataMedia);
        if ($result) {
            $this->logger->notice(
                'Item #{item_id}: derivative file for type "{type}" is created successfully.', // @translate
                ['item_id' => $itemId, 'type' => $type]
            );
        }
    }
}
