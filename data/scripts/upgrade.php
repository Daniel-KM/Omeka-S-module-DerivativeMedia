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
    $settings->set('derivativemedia_enable', ['audio', 'video']);
    $message = new Message(
        'An new option was added to enable specific converters.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'It is now possible to output a zip of all files of an item (format url: https://example.org/derivative/zip/{item_id}).' // @translate
    );
    $messenger->addSuccess($message);
}
