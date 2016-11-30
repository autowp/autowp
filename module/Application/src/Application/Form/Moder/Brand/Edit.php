<?php

namespace Application\Form\Moder\Brand;

use Zend\Form\Form;

class Edit extends Form
{
    private $languages = [];

    public function init()
    {
        $this->add([
            'name' => 'name',
            'type' => \Application\Form\Element\BrandName::class,
            'options' => [
                'readonly' => 'readonly'
            ]
        ]);

        foreach ($this->languages as $language) {
            $this->add([
                'name' => 'name' . $language,
                'type' => \Application\Form\Element\BrandName::class,
                'options' => [
                    'label' => 'Name ('.$language.')',
                ]
            ]);
        }

        $this->add([
            'name'    => 'full_name',
            'type'    => \Application\Form\Element\BrandFullName::class,
            'options' => []
        ]);
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
