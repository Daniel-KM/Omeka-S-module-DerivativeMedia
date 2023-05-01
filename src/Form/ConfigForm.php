<?php declare(strict_types=1);

namespace DerivativeMedia\Form;

use Doctrine\DBAL\Connection;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function init(): void
    {
        $this
            ->add([
                'name' => 'fieldset_derivative',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Create derivatives', // @translate
                ],
            ]);

        $fieldset = $this->get('fieldset_derivative');
        $fieldset
            ->add([
                'name' => 'item_sets',
                'type' => OmekaElement\ItemSetSelect::class,
                'options' => [
                    'label' => 'Item sets', // @translate
                ],
                'attributes' => [
                    'id' => 'item_sets',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'required' => false,
                    'data-placeholder' => 'Select one or more item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'ingesters',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Ingesters to process', // @translate
                    'empty_option' => 'All ingesters', // @translate
                    'value_options' => $this->listIngesters(),
                ],
                'attributes' => [
                    'id' => 'ingesters',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'placeholder' => 'Select ingesters to process', // @ translate
                    'data-placeholder' => 'Select ingesters to process', // @ translate
                ],
            ])
            ->add([
                'name' => 'renderers',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Renderers to process', // @translate
                    'empty_option' => 'All renderers', // @translate
                    'value_options' => $this->listRenderers(),
                ],
                'attributes' => [
                    'id' => 'renderers',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'placeholder' => 'Select renderers to process', // @ translate
                    'data-placeholder' => 'Select renderers to process', // @ translate
                ],
            ])
            ->add([
                'name' => 'media_types',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Media types to process', // @translate
                    'empty_option' => 'All media types', // @translate
                    'value_options' => $this->listMediaTypes(),
                ],
                'attributes' => [
                    'id' => 'media_types',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'placeholder' => 'Select media types to process', // @ translate
                    'data-placeholder' => 'Select media types to process', // @ translate
                ],
            ])
            ->add([
                'name' => 'media_ids',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Media ids', // @translate
                ],
                'attributes' => [
                    'id' => 'media_ids',
                    'placeholder' => '2-6 8 38-52 80-', // @ translate
                ],
            ])
            ->add([
                'name' => 'process_derivative',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Create derivative files in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process_derivative',
                    'value' => 'Process', // @translate
                ],
            ])
            ->add([
                'name' => 'process_metadata',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Store metadata for existing files in directories', // @translate
                    'info' => 'When files are created outside of Omeka and copied in the right directories (webm/, mp3/, etc.) with the right names (same as original and extension), Omeka should record some metadata to be able to render them.', // @translate
                ],
                'attributes' => [
                    'id' => 'process_metadata',
                    'value' => 'Update metadata', // @translate
                ],
            ]);

        // Available in module Derivative Media and IiifServer.
        $this
            ->add([
                'name' => 'fieldset_dimensions',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Store dimensions (module IiifServer)', // @translate
                ],
            ]);
        $fieldset = $this->get('fieldset_dimensions');
        $fieldset
            ->add([
                'name' => 'query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query to filter items to process', // @translate
                    'info' => 'This query will be used to select all items whose attached images, audio and video files will be prepared in the background.', // @translate
                    'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
                ],
                'attributes' => [
                    'id' => 'query',
                ],
            ])
            ->add([
                'name' => 'process_dimensions',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Prepare dimensions for images, audio and videos attached to items selected above in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process_dimensions',
                    'value' => 'Process', // @translate
                ],
            ])
        ;

        $this->getInputFilter()
            ->get('fieldset_derivative')
            ->add([
                'name' => 'item_sets',
                'required' => false,
            ])
            ->add([
                'name' => 'ingesters',
                'required' => false,
            ])
            ->add([
                'name' => 'renderers',
                'required' => false,
            ])
            ->add([
                'name' => 'media_types',
                'required' => false,
            ]);
    }

    /**
     * @return array
     */
    protected function listIngesters()
    {
        $sql = 'SELECT DISTINCT(ingester) FROM media ORDER BY ingester';
        $result = $this->connection->executeQuery($sql)->fetchFirstColumn();
        return ['' => 'All ingesters'] // @translate
            + array_combine($result, $result);
    }

    /**
     * @return array
     */
    protected function listRenderers()
    {
        $sql = 'SELECT DISTINCT(renderer) FROM media ORDER BY renderer';
        $result = $this->connection->executeQuery($sql)->fetchFirstColumn();
        return ['' => 'All renderers'] // @translate
            + array_combine($result, $result);
    }

    /**
     * @return array
     */
    protected function listMediaTypes()
    {
        $sql = 'SELECT DISTINCT(media_type) FROM media WHERE media_type IS NOT NULL AND media_type != "" ORDER BY media_type';
        $result = $this->connection->executeQuery($sql)->fetchFirstColumn();
        return ['' => 'All media types'] // @translate
            + array_combine($result, $result);
    }

    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }
}
