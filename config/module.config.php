<?php declare(strict_types=1);

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
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'checkFfmpeg' => Mvc\Controller\Plugin\CheckFfmpeg::class,
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
            'derivativemedia_converters_audio' => [
                'mp3/{filename}.mp3' => '-c copy -c:a libmp3lame -qscale:a 2',
                'ogg/{filename}.ogg' => '-c copy -vn -c:a libopus',
            ],
            'derivativemedia_converters_video' => [
                '# The webm converter is designed for modern browsers. Keep it first if used.' => '',
                'webm/{filename}.webm' => '-c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus',
                '# This format keeps the original quality and is compatible with almost all browsers.' => '',
                'mp4/{filename}.mp4' => "-c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset medium -tune film -pix_fmt yuv420p -c:a libmp3lame -qscale:a 2",
            ],
            'derivativemedia_append_original_audio' => false,
            'derivativemedia_append_original_video' => false,
        ],
    ],
];
