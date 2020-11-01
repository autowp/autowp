<?php

namespace Application\Form\Moder;

use Laminas\Form\Exception;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Traversable;

class ItemLanguages extends Form implements InputFilterProviderInterface
{
    private array $languages = [];

    public function __construct(?string $name = null, array $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        foreach ($this->languages as $language) {
            $this->add([
                'name'    => $language,
                'type'    => ItemLanguage::class,
                'options' => [
                    'label' => $language,
                ],
            ]);
            /** @var Form $element */
            $element = $this->get($language);
            $element->setWrapElements(true)->prepare();
        }

        $this->setAttribute('method', 'post');
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - use_as_base_fieldset: is this fieldset use as the base fieldset?
     *
     * @param  array|Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options): self
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
     * {@link \Laminas\InputFilter\Factory::createInputFilter()}.
     */
    public function getInputFilterSpecification(): array
    {
        return [];
    }
}
