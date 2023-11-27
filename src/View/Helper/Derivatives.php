<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Derivatives extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/derivatives';

    /**
     * Get the list of derivatives of a resource as html.
     *
     *@param array $options Managed options:
     * - template (string): the template to use instead of the default one.
     * Other options are passed to the template.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();

        $options += [
            'site' => null,
            'derivatives' => $view->derivativeList($resource),
            'template' => self::PARTIAL_NAME,
        ];

        $vars = ['resource' => $resource] + $options;

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
