<?php

namespace Application;

use Application\Model\Picture;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Renderer\PhpRenderer;

use function count;
use function implode;
use function mb_strtoupper;
use function mb_substr;

class PictureNameFormatter
{
    private TranslatorInterface $translator;

    private ItemNameFormatter $itemNameFormatter;

    private PhpRenderer $renderer;

    private Picture $picture;

    public function __construct(
        TranslatorInterface $translator,
        PhpRenderer $renderer,
        ItemNameFormatter $itemNameFormatter,
        Picture $picture
    ) {
        $this->translator        = $translator;
        $this->renderer          = $renderer;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->picture           = $picture;
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    private function translate($string, $language)
    {
        return $this->translator->translate($string, 'default', $language);
    }

    public function format($picture, $language)
    {
        if (isset($picture['name']) && $picture['name']) {
            return $picture['name'];
        }

        $result = [];

        if (count($picture['items']) > 1) {
            foreach ($picture['items'] as $item) {
                $result[] = $item['name'];
            }

            return implode(', ', $result);
        } elseif (count($picture['items']) === 1) {
            $item = $picture['items'][0];

            $result = [];
            if ($item['perspective']) {
                $result[] = self::mbUcfirst($this->translate($item['perspective'], $language));
            }

            $result[] = $this->itemNameFormatter->format($item, $language);

            return implode(' ', $result);
        }

        return 'Picture';
    }

    public function formatHtml(array $picture, $language)
    {
        if (isset($picture['name']) && $picture['name']) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return $this->renderer->escapeHtml($picture['name']);
        }

        $result = [];
        if (count($picture['items']) > 1) {
            foreach ($picture['items'] as $item) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $result[] = $this->renderer->escapeHtml($item['name']);
            }
            return implode(', ', $result);
        } elseif (count($picture['items']) === 1) {
            $item = $picture['items'][0];

            $result = [];
            if ($item['perspective']) {
                $perspective = $this->translate($item['perspective'], $language);
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $result[] = $this->renderer->escapeHtml(self::mbUcfirst($perspective));
            }

            $result[] = $this->itemNameFormatter->formatHtml($item, $language);
            return implode(' ', $result);
        }

        return 'Picture';
    }
}
