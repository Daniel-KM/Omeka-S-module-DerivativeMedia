<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeItem extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform(): void
    {
        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $itemId = $this->getArg('itemId');
        try {
            /** @var \Omeka\Entity\Item $item */
            $item = $this->getServiceLocator()->get('Omeka\ApiManager')->read('items', ['id' => $itemId], [], ['initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
            $this->logger->err(
                'No item #{item_id}: no derivative media to create.', // @translate
                ['item_id' => $itemId]
            );
            return;
        }

        foreach ($item->getMedia() as $media) {
            if ($this->isManaged($media)) {
                $this->derivateMedia($media);
            }
        }
    }
}
