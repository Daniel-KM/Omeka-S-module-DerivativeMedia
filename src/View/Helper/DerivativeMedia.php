<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class DerivativeMedia extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/derivative-media';

    /**
     * Get the list of derivative media of a resource as html.
     *
     * Managed options:
     * - template
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();

        $vars = [
            'resource' => $resource,
            'options' => $options,
        ];

        $assetUrl = $view->plugin('assetUrl');
        /*
        $view->headLink()
            ->prependStylesheet($assetUrl('css/derivative-media.css', 'DerivativeMedia'));
        */
        $view->headScript()
            ->appendFile($assetUrl('js/derivative-media.js', 'DerivativeMedia'), 'text/javascript', ['defer' => 'defer']);

        $template = $options['template'] ?? self::PARTIAL_NAME;
        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }
}
