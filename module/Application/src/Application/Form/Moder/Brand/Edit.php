<?php

namespace Application\Form\Moder\Brand;

use Zend\Form\Form;

use Application\Form\Element\BrandName;
use Application\Form\Element\BrandFullName;

class Edit extends Form
{
    private $languages = [];

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $this->add([
            'name' => 'caption',
            'type' => BrandName::class,
            'options' => [
                'readonly' => 'readonly'
            ]
        ]);

        foreach ($this->languages as $language) {
            $this->add([
                'name' => 'name'.$language,
                'type' => BrandName::class,
                'options' => [
                    'label' => 'Name ('.$language.')',
                ]
            ]);
        }

        $this->add([
            'name' => 'full_caption',
            'type' => BrandFullName::class,
            'options' => []
        ]);

        $this->setAttribute('method', 'post');
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

        parent::setOptions($options);

        return $this;
    }
}