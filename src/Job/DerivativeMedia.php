<?php

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeMedia extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform()
    {
        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $mediaId = $this->getArg('mediaId');
        try {
            /** @var \Omeka\Entity\Media $media */
            $media = $this->getServiceLocator()->get('Omeka\ApiManager')->read('media', ['id' => $mediaId], [], ['initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
            $this->logger->err(
                'No media #{media_id}: no derivative media to create.', // @translate
                ['media_id' => $mediaId]
            );
            return;
        }

        if (!$this->isManaged($media)) {
            $this->logger->warn(
                'Media #{media_id}: not an audio or video file.', // @translate
                ['media_id' => $mediaId]
            );
            return;
        }

        $this->derivateMedia($media);
    }
}
