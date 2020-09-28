<?php

namespace DerivativeMedia\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * @see \Omeka\Media\FileRenderer\AudioRenderer
 */
class AudioRenderer implements RendererInterface
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
            $format = "<audio%s>\n%s\n</audio>";
            // Append the original file if wanted.
            if ($view->setting('derivativemedia_append_original_audio', false)) {
                $sources .= '<source src="' . $escapeAttr($originalUrl) . '" type="' . $media->mediaType() . '"/>' . "\n";
            }
        } else {
            $format = '<audio%s>%s</audio>';
            $attrs .= sprintf(' src="%s"', $escapeAttr($originalUrl));
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
