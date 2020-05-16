<?php

namespace Application;

use ArrayAccess;
use Exception;
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

    public function __construct(
        TranslatorInterface $translator,
        PhpRenderer $renderer,
        ItemNameFormatter $itemNameFormatter
    ) {
        $this->translator        = $translator;
        $this->renderer          = $renderer;
        $this->itemNameFormatter = $itemNameFormatter;
    }

    private static function mbUcfirst(string $str): string
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    private function translate(string $string, string $language): string
    {
        return $this->translator->translate($string, 'default', $language);
    }

    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    public function format($picture, string $language): string
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

    public function formatHtml(array $picture, string $language): string
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
