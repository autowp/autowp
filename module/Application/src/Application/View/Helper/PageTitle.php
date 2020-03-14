<?php

namespace Application\View\Helper;

use Laminas\View\Exception;
use Laminas\View\Helper\Placeholder\Container\AbstractContainer;
use Laminas\View\Helper\Placeholder\Container\AbstractStandalone;
use Laminas\View\Helper\TranslatorAwareTrait;

use function implode;
use function in_array;

class PageTitle extends AbstractStandalone
{
    use TranslatorAwareTrait;

    /**
     * Registry key for placeholder
     */
    protected string $regKey = 'Application_View_Helper_PageTitle2';

    /**
     * Default title rendering order (i.e. order in which each title attached)
     */
    protected string $defaultAttachOrder;

    /**
     * Retrieve placeholder for title element and optionally set state
     *
     * @param null|string $title
     * @param null|string $setType
     */
    public function __invoke($title = null, $setType = null): self
    {
        if (null === $setType) {
            $setType = null === $this->getDefaultAttachOrder()
                ? AbstractContainer::APPEND
                : $this->getDefaultAttachOrder();
        }

        $title = (string) $title;
        if ($title !== '') {
            if ($setType === AbstractContainer::SET) {
                $this->set($title);
            } elseif ($setType === AbstractContainer::PREPEND) {
                $this->prepend($title);
            } else {
                $this->append($title);
            }
        }

        return $this;
    }

    /**
     * Render title (wrapped by title tag)
     */
    public function toString(?string $indent = null): string
    {
        $indent = null !== $indent
            ? $this->getWhitespace($indent)
            : $this->getIndent();

        $output = $this->renderTitle();

        return $output ? $indent . '<div class="page-header"><h1>' . $output . '</h1></div>' : '';
    }

    /**
     * Render title string
     */
    public function renderTitle(): string
    {
        $items = [];

        $itemCallback = $this->getTitleItemCallback();
        foreach ($this as $item) {
            $items[] = $itemCallback($item);
        }

        $separator = $this->getSeparator();
        $output    = '';

        $prefix = $this->getPrefix();
        if ($prefix) {
            $output .= $prefix;
        }

        $output .= implode($separator, $items);

        $postfix = $this->getPostfix();
        if ($postfix) {
            $output .= $postfix;
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $output = $this->autoEscape ? $this->escape($output) : $output;

        return $output;
    }

    /**
     * Set a default order to add titles
     *
     * @param string $setType
     */
    public function setDefaultAttachOrder($setType): self
    {
        if (
            ! in_array($setType, [
                AbstractContainer::APPEND,
                AbstractContainer::SET,
                AbstractContainer::PREPEND,
            ])
        ) {
            throw new Exception\DomainException(
                "You must use a valid attach order: 'PREPEND', 'APPEND' or 'SET'"
            );
        }
        $this->defaultAttachOrder = $setType;

        return $this;
    }

    /**
     * Get the default attach order, if any.
     */
    public function getDefaultAttachOrder(): ?string
    {
        return $this->defaultAttachOrder ?? null;
    }

    /**
     * Create and return a callback for normalizing title items.
     *
     * If translation is not enabled, or no translator is present, returns a
     * callable that simply returns the provided item; otherwise, returns a
     * callable that returns a translation of the provided item.
     *
     * @return callable
     */
    private function getTitleItemCallback()
    {
        if (! $this->isTranslatorEnabled() || ! $this->hasTranslator()) {
            return function ($item) {
                return $item;
            };
        }

        $translator = $this->getTranslator();
        $textDomain = $this->getTranslatorTextDomain();
        return function ($item) use ($translator, $textDomain) {
            return $translator->translate($item, $textDomain);
        };
    }
}
