<?php

namespace DerivativeMedia;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Log\Stdlib\PsrMessage;
use Omeka\Entity\Media;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

/**
 * Derivative Media
 *
 * Create derivative audio/video media files for cross-browser compatibility.
 *
 * @copyright Daniel Berthereau, 2020
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected $dependency = 'Log';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    protected function preInstall()
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!is_dir($basePath) || !is_readable($basePath) || !is_writable($basePath)) {
            $message = new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath]
            );
            throw new ModuleCannotInstallException($message);
        }

        // TODO Use Omeka cli.
        // @link http://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
        if ((int) shell_exec('hash ffmpeg 2>&- || echo 1')) {
            $services->get('Omeka\Logger')->err('Command "ffmpeg" not found.'); // @translate
            $t = $services->get('MvcTranslator');
            throw new ModuleCannotInstallException($t->translate('The command-line utility "ffmpeg" must be installed first and must be available in the cli path.')); // @translate
        }
    }

    public function warnUninstall(Event $event)
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING:'); // @translate
        $html .= '</strong>';
        $html .= '</p>';

        $html .= '<p>';
        // TODO Remove all files one by one, because the base path of files may have been changed.
        $html .= new PsrMessage(
            'All derivative files will be kept in the folders specified in the config inside {path}.', // @translate
            ['path' => $basePath]
        );
        $html .= '</p>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Note: When an item is saved manually, no event is triggered for media.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'afterSaveItem']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'afterSaveItem']
        );

        // TODO "api.create.post" seems never to occur for media. Remove event?
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.post',
            [$this, 'afterSaveMedia']
        );

        $sharedEventManager->attach(
            \Omeka\Entity\Media::class,
            'entity.remove.post',
            [$this, 'afterDeleteMedia'],
            // Before the deletion of the media via the core method.
            10
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }

    public function afterSaveItem(Event $event)
    {
        $item = $event->getParam('response')->getContent();
        foreach ($item->getMedia() as $media) {
            $this->processMedia($media);
        }
    }

    public function afterSaveMedia(Event $event)
    {
        $media = $event->getParam('response')->getContent();
        $this->processMedia($media);
    }

    protected function processMedia(Media $media)
    {
        if (!$this->isManaged($media)) {
            return;
        }

        $data = $media->getData();

        // Don't reprocess derivative.
        if (!empty($data['derivative'])) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $converters = $settings->get('derivativemedia_converters');
        if (!$converters) {
            return;
        }

        /** @var \Omeka\File\Store\StoreInterface $store */
        $store = $services->get('Omeka\File\Store');
        if (!($store instanceof \Omeka\File\Store\Local)) {
            $services->get('Omeka\Logger')->err(
                '[Derivative Media] This module requires a local store currently.' // @translate
            );
            return;
        }

        $args = [];
        $args['mediaId'] = $media->getId();
        $dispatcher = $services->get('ControllerPluginManager')->get('jobDispatcher');
        $dispatcher()->dispatch(\DerivativeMedia\Job\DerivativeMedia::class, $args);
    }

    public function afterDeleteMedia(Event $event)
    {
        /** @var \Omeka\Entity\Media $media */
        $media = $event->getTarget();
        if (!$this->isManaged($media)) {
            return;
        }

        $data = $media->getData();
        if (empty($data['derivative'])) {
            return;
        }

        $services = $this->getServiceLocator();

        /** @var \Omeka\File\Store\StoreInterface $store */
        $store = $services->get('Omeka\File\Store');
        foreach ($data['derivative'] as $folder => $derivative) {
            $storagePath = $folder . '/' . $derivative['filename'];
            $store->delete($storagePath);
        }
    }

    protected function isManaged(Media $media)
    {
        return $media->hasOriginal()
            && $media->getRenderer() === 'file'
            && in_array(strtok($media->getMediaType(), '/'), ['audio', 'video']);
    }
}
