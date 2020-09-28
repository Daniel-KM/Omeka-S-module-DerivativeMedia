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
                'name' => 'derivativemedia_converters',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Converters', // @translate
                    'info' => 'Each converter is one row with a unique extension (may be double), then "=", and the ffmpeg command (without file).', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia',
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'derivativemedia_converters',
                    'rows' => 5,
                ],
            ])
        ;
    }
}
