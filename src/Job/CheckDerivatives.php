<?php declare(strict_types=1);

namespace DerivativeMedia\Job;

use Common\Stdlib\FileLock;
use DerivativeMedia\Module;
use Omeka\Job\AbstractJob;
use ZipArchive;

/**
 * Remove the leftovers of interrupted builds and the invalid item derivatives.
 *
 * The removed derivatives are rebuilt once on next request or by a job.
 */
class CheckDerivatives extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('derivative/check/job_' . $this->job->getId());
        $logger->addProcessor($referenceIdProcessor);

        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $cli = $services->get('Omeka\Cli');
        $hasPdfinfo = (bool) $cli->getCommandPath('pdfinfo');
        $fileLock = new FileLock();

        // Files of the builds stopped one day ago or more.
        $oldTime = time() - 86400;

        $dirs = [];
        foreach (Module::DERIVATIVES as $derivative) {
            if ($derivative['level'] === 'item' && !empty($derivative['dir'])) {
                $dirs[$derivative['dir']] = $derivative['extension'];
            }
        }

        $totalTemp = 0;
        $totalInvalid = 0;
        foreach ($dirs as $dir => $extension) {
            foreach (glob($basePath . '/' . $dir . '/*') ?: [] as $filepath) {
                if ($this->shouldStop()) {
                    $logger->warn(
                        'The job was stopped.' // @translate
                    );
                    return;
                }
                if (!is_file($filepath)) {
                    continue;
                }
                $filename = basename($filepath);
                // Temp files of the previous and current versions.
                $isTemp = strpos($filename, '.tmp.') !== false
                    || FileLock::isTempOrLockFile($filepath);
                if ($isTemp) {
                    $mainFilepath = preg_replace('~\.(\d+-[0-9a-f]{8}\.)?tmp\.~', '.', $filepath);
                    $mainFilepath = preg_replace('~\.lock$~', '', $mainFilepath);
                    if (filemtime($filepath) < $oldTime && !$fileLock->isLocked($mainFilepath)) {
                        @unlink($filepath);
                        ++$totalTemp;
                    }
                    continue;
                }
                if (pathinfo($filename, PATHINFO_EXTENSION) !== $extension
                    || $fileLock->isLocked($filepath)
                    || $this->isValid($filepath, $extension, $hasPdfinfo ? $cli : null)
                ) {
                    continue;
                }
                $logger->warn(
                    'The derivative "{file}" is invalid, so it is removed to be rebuilt.', // @translate
                    ['file' => $dir . '/' . $filename]
                );
                @unlink($filepath);
                ++$totalInvalid;
            }
        }

        $logger->notice(
            'Check of derivatives: {count_temp} temp files and {count_invalid} invalid derivatives removed.', // @translate
            ['count_temp' => $totalTemp, 'count_invalid' => $totalInvalid]
        );
    }

    protected function isValid(string $filepath, string $extension, ?\Omeka\Stdlib\Cli $cli): bool
    {
        if (!filesize($filepath)) {
            return false;
        }
        if ($extension === 'zip') {
            $zip = new ZipArchive();
            $result = $zip->open($filepath, ZipArchive::CHECKCONS) === true;
            if ($result) {
                $zip->close();
            }
            return $result;
        }
        if ($extension === 'pdf' && $cli) {
            $output = $cli->execute(sprintf('pdfinfo %s 2>&1', escapeshellarg($filepath)));
            return $output !== false && preg_match('/^Pages:\s+[1-9]/m', $output);
        }
        if ($extension === 'json') {
            return is_array(json_decode((string) file_get_contents($filepath), true));
        }
        return true;
    }
}
