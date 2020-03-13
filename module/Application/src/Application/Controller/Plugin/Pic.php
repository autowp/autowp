<?php

namespace Application\Controller\Plugin;

use Application\Model\Picture;
use Application\PictureNameFormatter;
use ArrayAccess;
use ArrayObject;
use Exception;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Pic extends AbstractPlugin
{
    private PictureNameFormatter $pictureNameFormatter;

    private Picture $picture;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        Picture $picture
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->picture              = $picture;
    }

    /**
     * @param array|ArrayAccess $pictureRow
     * @throws Exception
     */
    public function name($pictureRow, string $language): string
    {
        if ($pictureRow instanceof ArrayObject) {
            $pictureRow = (array) $pictureRow;
        }

        $names = $this->picture->getNameData([$pictureRow], [
            'language' => $language,
            'large'    => true,
        ]);
        $name  = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
