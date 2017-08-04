<?php

namespace Application;

use Zend\View\Renderer\PhpRenderer;

use Application\Model\DbTable;

class PictureNameFormatter
{
    private $translator;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var PhpRenderer
     */
    private $renderer;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        $translator,
        PhpRenderer $renderer,
        ItemNameFormatter $itemNameFormatter,
        DbTable\Picture $pictureTable
    ) {

        $this->translator = $translator;
        $this->renderer = $renderer;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->pictureTable = $pictureTable;
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
        if ($picture instanceof \Autowp\Commons\Db\Table\Row) {
            $names = $this->pictureTable->getNameData([$picture->toArray()], [
                'language' => $language,
                'large'    => true
            ]);
            $picture = $names[$picture['id']];
        }

        if (isset($picture['name']) && $picture['name']) {
            return $picture['name'];
        }

        $result = [];

        if (count($picture['items']) > 1) {
            foreach ($picture['items'] as $item) {
                $result[] = $item['name'];
            }

            return implode(', ', $result);
        } elseif (count($picture['items']) == 1) {
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
            return $this->renderer->escapeHtml($picture['name']);
        }

        $result = [];
        if (count($picture['items']) > 1) {
            foreach ($picture['items'] as $item) {
                $result[] = $this->renderer->escapeHtml($item['name']);
            }
            return implode(', ', $result);
        } elseif (count($picture['items']) == 1) {
            $item = $picture['items'][0];

            $result = [];
            if ($item['perspective']) {
                $perspective = $this->translate($item['perspective'], $language);
                $result[] = $this->renderer->escapeHtml(self::mbUcfirst($perspective, $language));
            }

            $result[] = $this->itemNameFormatter->formatHtml($item, $language);
            return implode(' ', $result);
        }

        return 'Picture';
    }
}
