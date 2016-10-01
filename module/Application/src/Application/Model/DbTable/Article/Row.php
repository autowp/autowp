<?php

namespace Application\Model\DbTable\Article;

use Application\Db\Table\Row;
use Application\Model\DbTable\Article;

class Row extends Row
{
    public function previewExists()
    {
        return $this->preview_width && $this->preview_height && strlen($this->preview_filename);
    }

    /**
     * @deprecated
     * @param bool $absolute
     * @return string
     */
    public function getPreviewUrl($absolute = false)
    {
        if ($this->previewExists()) {
            return ($absolute ? HOST : '/') . Article::PREVIEW_CAT_PATH . $this->preview_filename;
        }

        return null;
    }
}