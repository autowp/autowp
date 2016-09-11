<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Form\Element\EngineName;
use Application\Form\Element\Brand;

class EngineAdd extends Form implements InputFilterProviderInterface
{
    private $disableBrand = false;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        $elements = [];

        if (!$this->disableBrand) {
            $elements[] = [
                'name' => 'brand_id',
                'type' => Brand::class
            ];
        }

        $elements[] = [
            'name' => 'caption',
            'type' => EngineName::class,
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('legend', 'Двигатель');
        $this->setAttribute('description', 'Новый двигатель');
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
        if (isset($options['disableBrand'])) {
            $this->disableBrand = (bool)$options['disableBrand'];
            unset($options['disableBrand']);
        }

        parent::setOptions($options);

        return $this;
    }

    /**
     * Should return an array specification compatible with
     * {@link Zend\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification()
    {
        $spec = [
            'caption' => [
                'required' => true
            ]
        ];
        if (!$this->disableBrand) {
            $spec['brand_id'] = [
                'required' => true
            ];
        }

        return $spec;
    }
}