<?php declare(strict_types=1);

namespace DerivativeMedia;

use Omeka\Stdlib\Message;

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
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.4.4', '<')) {
    $settings->set('derivativemedia_enable', []);
    $message = new Message(
        'A new option was added to enable specific converters.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'It is now possible to output a zip of all files of an item (format url: https://example.org/derivative/zip/{item_id}).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.4', '<')) {
    $settings->set('derivativemedia_update', 'existing');
    $message = new Message(
        'Many new formats have been added: zip, text, alto, iiif, pdf.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'A resource page block allows to display the list of available derivatives of a resource.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'Check %1$snew settings%2$s.', // @translate
        sprintf('<a href="%s">', $url('admin/default', ['controller' => 'setting'], ['fragment' => 'derivative-media'])),
        '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}
