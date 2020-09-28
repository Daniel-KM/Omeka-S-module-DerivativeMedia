<?php

namespace DerivativeMedia;

return [
    'file_renderers' => [
        'invokables' => [
            'audio' => Media\FileRenderer\AudioRenderer::class,
            'video' => Media\FileRenderer\VideoRenderer::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\ArrayTextarea::class => Form\Element\ArrayTextarea::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'derivativemedia' => [
        'settings' => [
            'derivativemedia_converters' => [
                'mp4/{filename}.mp4' => "-c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset medium -tune film -pix_fmt yuv420p",
                'webm/{filename}.webm' => '-c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -acodec libvorbis -deadline good -pix_fmt yuv420p',
            ],
        ],
    ],
];
