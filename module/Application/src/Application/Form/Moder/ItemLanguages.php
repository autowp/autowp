<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class ItemLanguages extends Form implements InputFilterProviderInterface
{
    private $languages = [];

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        $elements = [];

        foreach ($this->languages as $language) {
            $this->add([
                'name'    => $language,
                'type'    => ItemLanguage::class,
                'options' => [
                    'label' => $language
                ]
            ]);
            $this->get($language)->setWrapElements(true)->prepare();
        }

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

    /**
     * Should return an array specification compatible with
     * {@link Zend\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification()
    {
        return [];
    }
}
