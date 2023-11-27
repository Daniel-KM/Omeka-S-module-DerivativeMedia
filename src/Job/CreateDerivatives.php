<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class CreateDerivatives extends AbstractJob
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
                'No item #{item_id}: no derivative to create.', // @translate
                ['item_id' => $itemId]
            );
            return;
        }

        $type = $this->getArg('type');
        $types = is_array($type) ? $type : [$type];
        $types = array_filter($types);

        if (empty($types)) {
            $this->logger->err(
                'No types to process.' // @translate
            );
            return;
        }

        // Recheck types with enabled types.
        $settings = $services->get('Omeka\Settings');
        $enabled = $settings->get('derivativemedia_enable', []);

        $types = array_intersect($types, array_keys(Module::DERIVATIVES), $enabled);
        $types = array_combine($types, $types);
        unset($types['audio'], $types['video'], $types['pdf_media']);

        if (empty($types)) {
            $this->logger->err(
                'No enabled type of derivative to process.' // @translate
            );
            return;
        }

        // Warning: dataMedia should be provided only when a single type should
        // be processed, because the list of medias is different.
        $dataMedia = count($types) === 1 ? $this->getArg('dataMedia', []) : [];

        /** @var \DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative $createDerivative */
        $plugins = $services->get('ControllerPluginManager');
        $createDerivative = $plugins->get('createDerivative');

        foreach ($types as $type) {
            $filepath = $this->itemFilepath($item, $type);

            // Messages are already logged, except in case of success.
            $result = $createDerivative($type, $filepath, $item, $dataMedia);
            if ($result) {
                $this->logger->notice(
                    'Item #{item_id}: derivative file for type "{type}" is created successfully.', // @translate
                    ['item_id' => $itemId, 'type' => $type]
                );
            }
        }
    }
}
