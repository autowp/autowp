<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\DbTable;
use Application\PictureNameFormatter;

class Pic extends AbstractHtmlElement
{
    /**
     * @var \Autowp\Commons\Db\Table\Row
     */
    private $picture = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        DbTable\Picture $pictureTable
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->pictureTable = $pictureTable;
    }

    public function __invoke(\Autowp\Commons\Db\Table\Row $picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            return $this->view->url('picture/picture', [
                'picture_id' => $this->picture['identity']
            ]);
        }
        return false;
    }

    public function htmlTitle(array $picture)
    {
        return $this->pictureNameFormatter->formatHtml($picture, $this->view->language());
    }

    public function textTitle(array $picture)
    {
        return $this->pictureNameFormatter->format($picture, $this->view->language());
    }

    public function name($pictureRow, $language)
    {
        $names = $this->pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
