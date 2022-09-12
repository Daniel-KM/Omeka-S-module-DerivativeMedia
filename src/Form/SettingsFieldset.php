<?php declare(strict_types=1);

namespace DerivativeMedia\Form;

use Omeka\Form\Element\ArrayTextarea;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Derivative Media'; // @translate

    public function init(): void
    {
        $this
            ->setAttribute('id', 'derivative-media')
            ->add([
                'name' => 'derivativemedia_converters_audio',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Audio converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia#usage',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_audio',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'derivativemedia_append_original_audio',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append original audio', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_append_original_audio',
                ],
            ])
            ->add([
                'name' => 'derivativemedia_converters_video',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Video converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_video',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'derivativemedia_append_original_video',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append original video', // @translate
                ],
                'attributes' => [
                    'id' => 'derivativemedia_append_original_video',
                ],
            ])
        ;
    }
}
