<?php declare(strict_types=1);

namespace DerivativeMedia;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use DerivativeMedia\Form\ConfigForm;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Log\Stdlib\PsrMessage;
use Omeka\Entity\Media;
use Omeka\Module\Exception\ModuleCannotInstallException;

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

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
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

    public function warnUninstall(Event $event): void
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
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
            'Omeka\Controller\Admin\Media',
            'view.details',
            [$this, 'viewDetailsMedia']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'viewDetailsMedia']
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

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->init();
        $html = '<p>'
            . $renderer->translate('Set your parameters in main settings to recreate derivative medias.') // @translate
            . '</p>';
        $html .= '<p>'
            . $renderer->translate('This processor is available in module Bulk Check too.') // @translate
            . '</p>';
        $html .= $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        if (empty($params['process']) || $params['process'] !== $controller->translate('Process')) {
            $message = 'No job launched.'; // @translate
            $controller->messenger()->addWarning($message);
            return true;
        }

        unset($params['csrf']);
        unset($params['process']);
        $params['item_sets'] = $params['item_sets'] ?: [];
        $params['ingesters'] = $params['ingesters'] ?: [];
        $params['renderers'] = $params['renderers'] ?: [];
        $params['media_types'] = $params['media_types'] ?: [];

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\DerivativeMedia\Job\FileDerivativeMedia::class, $params);
        $message = new PsrMessage(
            'Creating derivative media in background ({link}job #{job_id}{link_end}, {link_log}logs{link_end})', // @translate
            [
                'link' => sprintf('<a href="%s">',
                    htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%1$s">', $this->isModuleActive('Log') ? $controller->url()->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]) :  $controller->url()->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
        return true;
    }

    public function viewDetailsMedia(Event $event): void
    {
        $view = $event->getTarget();
        $media = $view->resource;
        /* @var \Omeka\Api\Representation\MediaRepresentation $media */
        $data = $media->mediaData();
        if (empty($data) || empty($data['derivative'])) {
            return;
        }

        $hyperlink = $view->plugin('hyperlink');
        $basePath = $view->serverUrl($view->basePath('/files'));

        $links = '';
        foreach ($data['derivative'] as $folder => $derivative) {
            $links .= '<li>' . $hyperlink($folder, $basePath . '/' . $folder . '/' . $derivative['filename']) . "</li>\n";
        }
        $title = $view->escapeHtml($view->translate('Derivative medias')); // @translate
        echo <<<HTML
<style>
@media screen {
    .browse .derivative-media h4 {
        display: inline-block;
    }
    .browse .derivative-media ul {
        display: inline-block;
        padding-left: 6px;
    }
    .browse .sidebar .derivative-media ul,
    .show .derivative-media ul {
        padding-left: 0;
    }
    .derivative-media ul li {
        list-style: none;
        display: inline-block;
    }
    .derivative-media ul li:not(:last-child):after {
        content: ' · ';
    }
}
</style>
<div class="meta-group derivative-media">
    <h4>$title</h4>
    <ul>
        $links
    </ul>
</div>
HTML;
    }

    public function afterSaveItem(Event $event): void
    {
        $item = $event->getParam('response')->getContent();
        foreach ($item->getMedia() as $media) {
            $this->processMedia($media);
        }
    }

    public function afterSaveMedia(Event $event): void
    {
        $media = $event->getParam('response')->getContent();
        $this->processMedia($media);
    }

    protected function processMedia(Media $media): void
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

        $removeCommented = function ($v, $k) {
            return !empty($v) && mb_strlen(trim($k)) && mb_substr(trim($k), 0, 1) !== '#';
        };
        $settings = $services->get('Omeka\Settings');
        $convertersAudio = array_filter($settings->get('derivativemedia_converters_audio', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        $convertersVideo = array_filter($settings->get('derivativemedia_converters_video', []), $removeCommented, ARRAY_FILTER_USE_BOTH);
        if (!$convertersAudio && !$convertersVideo) {
            return;
        }
        $mainMediaType = strtok($media->getMediaType(), '/');
        if ($mainMediaType === 'audio' && !$convertersAudio) {
            return;
        }
        if ($mainMediaType === 'video' && !$convertersVideo) {
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

    public function afterDeleteMedia(Event $event): void
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
