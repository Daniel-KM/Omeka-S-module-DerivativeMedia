<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DerivativeMedia extends AbstractJob
{
    use DerivativeMediaTrait;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative-media/derivative-media/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $result = $this->initialize();
        if (!$result) {
            return;
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $mediaId = $this->getArg('media_id');
        if (!$mediaId) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No media was set, so no derivative to create.' // @translate
            );
            return;
        }

        try {
            /** @var \Omeka\Entity\Media|\DigitalObject\Entity\DigitalObject $media */
            $media = $api->read('media', ['id' => $mediaId], [], ['initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
            // Fallback to digital_objects: the job may be dispatched for a
            // resource id that lives in the digital_object table.
            try {
                $media = $api->read('digital_objects', ['id' => $mediaId], [], ['initialize' => false, 'finalize' => false])->getContent();
            } catch (\Throwable $e2) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                $this->logger->err(
                    'No media or digital object #{media_id}: no derivative media to create.', // @translate
                    ['media_id' => $mediaId]
                );
                return;
            }
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
