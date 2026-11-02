<?php declare(strict_types=1);

namespace DerivativeMedia;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$helpers = $services->get('ViewHelperManager');
$url = $helpers->get('url');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$configLocal = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.95')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.95'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

// Check the optional module Iiif Server for incompatibility, like install.
if ($this->isModuleActive('IiifServer') && !$this->isModuleVersionAtLeast('IiifServer', '3.6.35')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Iiif Server', '3.6.35'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare($oldVersion, '3.4.4', '<')) {
    $settings->set('derivativemedia_enable', []);
    $message = new PsrMessage(
        'A new option was added to enable specific converters.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'It is now possible to output a zip of all files of an item (format url: https://example.org/derivative/zip/{item_id}).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.5', '<')) {
    $settings->set('derivativemedia_update', 'existing');
    $message = new PsrMessage(
        'Many new formats have been added: zip, text, alto, iiif, pdf.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'A resource page block allows to display the list of available derivatives of a resource.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.8', '<')) {
    $message = new PsrMessage(
        'The module manages now http requests "Content Range" that allow to read files faster.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.9', '<')) {
    $settings->set('derivativemedia_converters_pdf', $configLocal['derivativemedia']['settings']['derivativemedia_converters_pdf']);

    $message = new PsrMessage(
        'Helpers "derivativeMedia" and "hasDerivative" were renamed "derivatives" and "derivativeList".' // @translate
    );
    $messenger->addNotice($message);

    $message = new PsrMessage(
        'The module manages now pdf files. Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.10', '<')) {
    // Check the optional module Iiif Server for incompatibility.
    if ($this->isModuleActive('IiifServer') && !$this->isModuleVersionAtLeast('IiifServer', '3.6.28')) {
        $message = new \Omeka\Stdlib\Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            'Iiif Server', '3.6.28'
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }

    $message = new PsrMessage(
        'It is now possible to run the job to create derivative and metadata by items. See {link_url}config form{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'module'], ['query' => ['id' => 'DerivativeMedia']]))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Settings were updated. You may check {link_url}them{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.12', '<')) {
    $message = new PsrMessage(
        'Helpers "derivativeMedia" and "hasDerivative" were renamed "derivatives" and "derivativeList".' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.14', '<')) {
    $settings->set('derivativemedia_converters_image', $configLocal['derivativemedia']['settings']['derivativemedia_converters_image']);
    $settings->set('derivativemedia_append_original_image', $configLocal['derivativemedia']['settings']['derivativemedia_append_original_image']);

    $message = new PsrMessage(
        'It is now possible to create image derivatives (webp, avif, etc.) via ImageMagick. Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_enable']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);

    $settings->set('derivativemedia_ocr_language', $configLocal['derivativemedia']['settings']['derivativemedia_ocr_language']);

    $message = new PsrMessage(
        'The pdf builder now adds an ocr text layer via ocrmypdf when available. Check {link_url}new settings{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_ocr_language']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.17', '<')) {
    $message = new PsrMessage(
        'A derivative is now built once, even when it is requested concurrently, and it is rebuilt only when the files of the medias change. A new task allows to remove the invalid derivatives and the leftovers of interrupted builds: it may be run once from the {link_url}config form{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'module', 'action' => 'configure'], ['query' => ['id' => 'DerivativeMedia']]))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);

    // The default webm converter uses all the cores of the server. A command
    // modified by the admin is kept.
    $oldWebm = '-c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus';
    $newWebm = $configLocal['derivativemedia']['settings']['derivativemedia_converters_video']['webm/{filename}.webm'];
    $converters = $settings->get('derivativemedia_converters_video');
    $isUpdated = is_array($converters)
        && ($converters['webm/{filename}.webm'] ?? null) === $oldWebm;
    if ($isUpdated) {
        $converters['webm/{filename}.webm'] = $newWebm;
        $settings->set('derivativemedia_converters_video', $converters);
    }
    $message = new PsrMessage(
        $isUpdated
            ? 'The webm converter uses now all the cores of the server with the options "-row-mt 1 -tile-columns 2 -threads 0". The default command was updated in the {link_url}video converters{link_end}.' // @translate
            : 'The webm converter uses now all the cores of the server with the options "-row-mt 1 -tile-columns 2 -threads 0". You may add them to the {link_url}video converters{link_end}.', // @translate
        ['link_url' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivativemedia_converters_video']))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addNotice($message);
}
