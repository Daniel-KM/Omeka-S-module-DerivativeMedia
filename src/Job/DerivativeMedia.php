<?php

namespace DerivativeMedia\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Entity\Media;
use Omeka\Job\AbstractJob;
use Zend\Filter\RealPath;

class DerivativeMedia extends AbstractJob
{
    /**
     * @var \Zend\Log\Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Omeka\File\Store\StoreInterface
     */
    protected $store;

    /**
     * @var \Omeka\File\TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Omeka\Stdlib\Cli
     */
    protected $cli;

    /**
     * @var array
     */
    protected $converters;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Zend\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/media/job_' . $this->job->getId());

        $settings = $services->get('Omeka\Settings');
        $this->converters = $settings->get('derivativemedia_converters', []);
        if (empty($this->converters)) {
            $this->logger->warn(
                'No converters: no derivative media to create.' // @translate
            );
            return;
        }

        // Note: ffmpeg supports urls as input and output.
        $this->store = $services->get('Omeka\File\Store');
        if (!($this->store instanceof \Omeka\File\Store\Local)) {
            $this->logger->err(
                'A local store is required to derivate media currently.' // @translate
            );
            return;
        }

        $mediaId = $this->getArg('mediaId');
        try {
            /** @var \Omeka\Entity\Media $media */
            $media = $services->get('Omeka\ApiManager')->read('media', ['id' => $mediaId], [], ['initialize' => false, 'finalize' => false])->getContent();
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

        $this->basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->cli = $services->get('Omeka\Cli');
        $this->tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $this->entityManager = $services->get('Omeka\EntityManager');

        $this->derivateMedia($media);
    }

    protected function derivateMedia(Media $media)
    {
        $filename = $media->getFilename();
        $sourcePath = $this->basePath . '/original/' . $filename;

        if (!file_exists($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file "{filename}" does not exist.', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return;
        }

        if (!is_readable($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file "{filename}" is not readable.', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return;
        }

        // Prepare media data.
        $mediaData = $media->getData();
        if (empty($mediaData)) {
            $mediaData = ['derivative' => []];
        } elseif (!isset($mediaData['derivative'])) {
            $mediaData['derivative'] = [];
        }

        $realpath = new RealPath(false);

        $storageId = $media->getStorageId();
        foreach ($this->converters as $pattern => $command) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Media #{media_id}: Process stopped.', // @translate
                    ['media_id' => $media->getId()]
                );
                return;
            }

            // Security checks.
            $command = trim($command);
            $pattern = trim($pattern);

            // FIXME How to secure admin-defined command? Move to config file? Create an intermediate shell script? Currently, most important characters are forbidden already and righs are the web server ones.
            if (!mb_strlen($command)
                || mb_strpos($command, 'sudo') !== false
                || mb_strpos($command, '$') !== false
                || mb_strpos($command, '<') !== false
                || mb_strpos($command, '>') !== false
                || mb_strpos($command, ';') !== false
                || mb_strpos($command, '&') !== false
                || mb_strpos($command, '|') !== false
                || mb_strpos($command, '%') !== false
                || mb_strpos($command, '"') !== false
                || mb_strpos($command, '\\') !== false
                || mb_strpos($command, '..') !== false
            ) {
                $this->logger->err(
                    'Media #{media_id}: the derivative command "{command}" contains forbidden characters [$<>;&|%"\\..].', // @translate
                    ['media_id' => $media->getId(), 'command' => $command]
                );
                return;
            }

            // Folder can be a sub one and file name may have a folder.
            $folder = mb_substr($pattern, 0, mb_strpos($pattern, '/{filename}.'));
            $basename = str_replace('{filename}', $storageId, mb_substr($pattern, mb_strpos($pattern, '/{filename}.') + 1));
            $storageName = $folder . '/' . $basename;
            $derivativePath = $this->basePath . '/' . $storageName;

            if (!mb_strlen($pattern)
                || mb_strpos($pattern, '/{filename}.') === false
                || mb_substr($pattern, 0, 1) === '/'
                || mb_strpos($pattern, '..') !== false
                || $derivativePath !== $realpath->filter($derivativePath)
            ) {
                $this->logger->err(
                    'Media #{media_id}: the derivative pattern "{pattern}" does not create a real path.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return;
            }

            if (file_exists($derivativePath) && !is_writeable($derivativePath)) {
                $this->logger->warn(
                    'Media #{media_id}: derivative media "{filename}" is not writeable.', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                continue;
            }

            // The path can contain a directory (module Archive repertory).
            // TODO To be removed: this is managed by the store anyway.
            $dirpath = dirname($derivativePath);
            if (file_exists($dirpath)) {
                if (!is_dir($dirpath) || !is_writable($dirpath)) {
                    $this->logger->warn(
                        'Media #{media_id}: derivative media "{filename}" is not writeable.', // @translate
                        ['media_id' => $media->getId(), 'filename' => $storageName]
                    );
                    continue;
                }
            } else {
                $result = @mkdir($dirpath, 0755, true);
                if (!$result) {
                    $this->logger->err(
                        'Media #{media_id}: derivative media "{filename}" is not writeable.', // @translate
                        ['media_id' => $media->getId(), 'filename' => $storageName]
                    );
                    continue;
                }
            }

            // Remove existing file in order to keep database sync in all cases.
            if (file_exists($derivativePath) || isset($mediaData['derivative'][$folder])) {
                $this->store->delete($storageName);
                unset($mediaData['derivative'][$folder]);
                $media->setData($mediaData);
                $this->entityManager->flush($media);
                $this->logger->info(
                    'Media #{media_id}: existing derivative media "{filename}" removed.', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
            }

            $this->logger->info(
                'Media #{media_id}: creating derivative media "{filename}".', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );

            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath() . '.' . pathinfo($basename, PATHINFO_EXTENSION);
            $tempFile->delete();
            $tempFile->setTempPath($tempPath);

            $command = 'ffmpeg -i ' . escapeshellarg($sourcePath) . ' ' . $command . ' ' . escapeshellarg($tempPath);

            $output = $this->cli->execute($command);

            // Errors are already logged only with proc_open(), not exec().
            if (false === $output) {
                $this->logger->err(
                    'Media #{media_id}: derivative media "{filename}" cannot be created.', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            if (strlen($output)) {
                $this->logger->info(
                    'Media #{media_id}: Output results for "{filename}":
{output}', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName, 'output' => $output]
                );
            }

            if (!file_exists($tempPath) || !filesize($tempPath)) {
                $this->logger->err(
                    'Media #{media_id}: derivative media "{filename}" is empty.', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            $mediaType = $tempFile->getMediaType();
            if (!in_array(strtok($mediaType, '/'), ['audio', 'video'])) {
                $this->logger->err(
                    'Media #{media_id}: derivative media "{filename}" is not audio/video, but "{mediatype}".', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName, 'mediatype' => $mediaType]
                );
                $tempFile->delete();
                return;
            }

            try {
                $this->store->put($tempPath, $storageName);
            } catch (\Omeka\File\Exception\RuntimeException $e) {
                $this->logger->err(
                    'Media #{media_id}: derivative media "{filename}" cannot be stored.', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            $tempFile->delete();

            $mediaData['derivative'][$folder]['filename'] = $basename;
            $mediaData['derivative'][$folder]['type'] = $mediaType;
            $media->setData($mediaData);
            $this->entityManager->flush($media);
            $this->logger->info(
                'Media #{media_id}: derivative media "{filename}" created.', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );
        }

        unset($media);
    }

    protected function isManaged(Media $media)
    {
        return $media->hasOriginal()
            && $media->getRenderer() === 'file'
            && in_array(strtok($media->getMediaType(), '/'), ['audio', 'video']);
    }
}
