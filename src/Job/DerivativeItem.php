<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeItem extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative-media/derivative-item/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        // A single item is kept for compatibility with previous versions.
        $itemIds = array_filter(array_map('intval', $this->getArg('item_ids') ?: [$this->getArg('item_id')]));
        if (!$itemIds) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No item was set, so no derivative media to create.' // @translate
            );
            return;
        }

        foreach ($itemIds as $itemId) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'The job was stopped.' // @translate
                );
                return;
            }
            try {
                /** @var \Omeka\Entity\Item $item */
                $item = $api->read('items', ['id' => $itemId], [], ['initialize' => false, 'finalize' => false])->getContent();
            } catch (NotFoundException $e) {
                $this->logger->err(
                    'No item #{item_id}: no derivative media to create.', // @translate
                    ['item_id' => $itemId]
                );
                continue;
            }

            foreach ($item->getMedia() as $media) {
                if ($this->isManaged($media)) {
                    $this->derivateMedia($media);
                }
            }
        }
    }
}
