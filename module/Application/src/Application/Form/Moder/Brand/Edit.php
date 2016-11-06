<?php

namespace Application\Form\Moder\Brand;

use Zend\Form\Form;

class Edit extends Form
{
    private $languages = [];

    private $elementsAdded = false;

    /**
     * Retrieve all attached elements
     *
     * Storage is an implementation detail of the concrete class.
     *
     * @return array|Traversable
     */
    public function getElements()
    {
        if (!$this->elementsAdded) {
            $this->elementsAdded = true;

            $this->add([
                'name' => 'caption',
                'type' => 'BrandName',
                'options' => [
                    'readonly' => 'readonly'
                ]
            ]);

            foreach ($this->languages as $language) {
                $this->add([
                    'name' => 'name' . $language,
                    'type' => 'BrandName',
                    'options' => [
                        'label' => 'Name ('.$language.')',
                    ]
                ]);
            }

            $this->add([
                'name'    => 'full_caption',
                'type'    => 'BrandFullName',
                'options' => []
            ]);
        }

        return parent::getElements();
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - use_as_base_fieldset: is this fieldset use as the base fieldset?
     *
     * @param  array|Traversable $options
     * @return Element|ElementInterface
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if (isset($options['languages'])) {
            $this->languages = $options['languages'];
            unset($options['languages']);
        }

        return parent::setOptions($options);
    }
}
