<?php

namespace DerivativeMedia\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * @see \Omeka\Media\FileRenderer\VideoRenderer
 */
class VideoRenderer implements RendererInterface
{
    const DEFAULT_OPTIONS = [
        'controls' => true,
    ];

    public function render(
        PhpRenderer $view,
        MediaRepresentation $media,
        array $options = []
    ) {
        $options = array_merge(self::DEFAULT_OPTIONS, $options);

        // Use a format compatible with html5 and xhtml.
        $escapeAttr = $view->plugin('escapeHtmlAttr');
        $attrs = '';
        $sources = '';

        $originalUrl = $media->originalUrl();

        $data = $media->mediaData();
        $hasDerivative = isset($data['derivative']) && count($data['derivative']);
        if ($hasDerivative) {
            $basePath = $view->serverUrl($view->basePath('/files'));
            foreach ($data['derivative'] as $folder => $derivative) {
                $sources .= '<source src="' . $escapeAttr($basePath . '/' . $folder . '/' . $derivative['filename']) . '"'
                    . (empty($derivative['type']) ? '' : ' type="' . $derivative['type'] . '"')
                    . "/>\n";
            }
            // Append the original file.
            $format = "<video%s>\n%s\n</video>";
            $sources .= '<source src="' . $escapeAttr($originalUrl) . '" type="' . $media->mediaType() . '"/>' . "\n";
        } else {
            $format = '<video%s>%s</video>';
            $attrs .= sprintf(' src="%s"', $escapeAttr($originalUrl));
        }

        if (isset($options['width'])) {
            $attrs .= sprintf(' width="%s"', (int) $options['width']);
        }
        if (isset($options['height'])) {
            $attrs .= sprintf(' height="%s"', (int) $options['height']);
        }
        if (isset($options['poster'])) {
            $attrs .= sprintf(' poster="%s"', $escapeAttr($options['poster']));
        }
        if (!empty($options['autoplay'])) {
            $attrs .= ' autoplay="autoplay"';
        }
        if (!empty($options['controls'])) {
            $attrs .= ' controls="controls"';
        }
        if (!empty($options['loop'])) {
            $attrs .= ' loop="loop"';
        }
        if (!empty($options['muted'])) {
            $attrs .= ' muted="muted"';
        }

        return sprintf(
            $format,
            $attrs,
            $sources . $view->hyperlink($media->filename(), $originalUrl)
        );
    }
}
