<?php

namespace DerivativeMedia\Form;

use DerivativeMedia\Form\Element\ArrayTextarea;
use Zend\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Derivative Media'; // @translate

    public function init()
    {
        $this
            ->add([
                'name' => 'derivativemedia_converters_audio',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Audio converters', // @translate
                    'info' => 'Each converter is one row with a filepath pattern, a "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters_audio',
                    'rows' => 5,
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
        ;
    }
}
