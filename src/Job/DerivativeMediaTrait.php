<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Laminas\Filter\RealPath;
use Omeka\Entity\Media;

trait DerivativeMediaTrait
{
    /**
     * @var \Laminas\Log\Logger
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

    protected function initialize()
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/media/job_' . $this->job->getId());

        $removeCommented = function ($v, $k) {
            return !empty($v) && mb_strlen(trim($k)) && mb_substr(trim($k), 0, 1) !== '#';
        };
        $settings = $services->get('Omeka\Settings');
        $this->converters['audio'] = array_filter($settings->get('derivativemedia_converters_audio', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        $this->converters['video'] = array_filter($settings->get('derivativemedia_converters_video', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        if (empty(array_filter($this->converters))) {
            return false;
        }

        // Security checks all converters one time.
        foreach ($this->converters as $type => $converters) {
            foreach ($converters as $pattern => $command) {
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
                        'The derivative command "{command}" for {type} contains forbidden characters [$<>;&|%"\\..].', // @translate
                        ['command' => $command, 'type' => $type]
                    );
                    return false;
                }

                if (!mb_strlen($pattern)
                    || mb_strpos($pattern, '/{filename}.') === false
                    || mb_substr($pattern, 0, 1) === '/'
                    || mb_strpos($pattern, '..') !== false
                ) {
                    $this->logger->err(
                        'The derivative pattern "{pattern}" for {type} does not create a real path.', // @translate
                        ['pattern' => $pattern, 'type' => $type]
                    );
                    return false;
                }
            }
        }

        // Note: ffmpeg supports urls as input and output.
        $this->store = $services->get('Omeka\File\Store');
        if (!($this->store instanceof \Omeka\File\Store\Local)) {
            $this->logger->err(
                'A local store is required to derivate media currently.' // @translate
            );
            return false;
        }

        $this->basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->cli = $services->get('Omeka\Cli');
        $this->tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $this->entityManager = $services->get('Omeka\EntityManager');

        return true;
    }

    protected function derivateMedia(Media $media)
    {
        $mainMediaType = strtok($media->getMediaType(), '/');
        if (empty($this->converters[$mainMediaType])) {
            return false;
        }

        $filename = $media->getFilename();
        $sourcePath = $this->basePath . '/original/' . $filename;

        if (!file_exists($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file does not exist ({filename})', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
        }

        if (!is_readable($sourcePath)) {
            $this->logger->warn(
                'Media #{media_id}: the original file is not readable ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => 'original/' . $filename]
            );
            return false;
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
        foreach ($this->converters[$mainMediaType] as $pattern => $command) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Media #{media_id}: Process stopped.', // @translate
                    ['media_id' => $media->getId()]
                );
                return false;
            }

            $command = trim($command);
            $pattern = trim($pattern);

            $folder = mb_substr($pattern, 0, mb_strpos($pattern, '/{filename}.'));
            $basename = str_replace('{filename}', $storageId, mb_substr($pattern, mb_strpos($pattern, '/{filename}.') + 1));
            $storageName = $folder . '/' . $basename;
            $derivativePath = $this->basePath . '/' . $storageName;

            // Another security check.
            if ($derivativePath !== $realpath->filter($derivativePath)) {
                $this->logger->err(
                    'Media #{media_id}: the derivative pattern "{pattern}" does not create a real path.', // @translate
                    ['media_id' => $media->getId(), 'pattern' => $pattern]
                );
                return false;
            }

            if (file_exists($derivativePath) && !is_writeable($derivativePath)) {
                $this->logger->warn(
                    'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
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
                        'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
                        ['media_id' => $media->getId(), 'filename' => $storageName]
                    );
                    continue;
                }
            } else {
                $result = @mkdir($dirpath, 0755, true);
                if (!$result) {
                    $this->logger->err(
                        'Media #{media_id}: derivative media is not writeable ({filename}).', // @translate
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
                    'Media #{media_id}: existing derivative media removed ({filename}).', // @translate
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
                    'Media #{media_id}: derivative media cannot be created ({filename}).', // @translate
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
                    'Media #{media_id}: derivative media is empty ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'filename' => $storageName]
                );
                $tempFile->delete();
                continue;
            }

            $mediaType = $tempFile->getMediaType();
            if (!in_array(strtok($mediaType, '/'), ['audio', 'video'])) {
                $this->logger->err(
                    'Media #{media_id}: derivative media is not audio/video, but "{mediatype}" ({filename}).', // @translate
                    ['media_id' => $media->getId(), 'mediatype' => $mediaType, 'filename' => $storageName]
                );
                $tempFile->delete();
                return false;
            }

            try {
                $this->store->put($tempPath, $storageName);
            } catch (\Omeka\File\Exception\RuntimeException $e) {
                $this->logger->err(
                    'Media #{media_id}: derivative media cannot be stored ({filename}).', // @translate
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
                'Media #{media_id}: derivative media created ({filename}).', // @translate
                ['media_id' => $media->getId(), 'filename' => $storageName]
            );
        }

        unset($media);
        return true;
    }

    protected function isManaged(Media $media)
    {
        return $media->hasOriginal()
            && $media->getRenderer() === 'file'
            && in_array(strtok($media->getMediaType(), '/'), ['audio', 'video']);
    }
}
