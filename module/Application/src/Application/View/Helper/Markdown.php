<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHtmlElement;
use Michelf\Markdown as MichelfMarkdown;

class Markdown extends AbstractHtmlElement
{
    public function __invoke(string $text): string
    {
        return MichelfMarkdown::defaultTransform($text);
    }
}
