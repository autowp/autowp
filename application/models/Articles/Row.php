<?php

use Application\Db\Table\Row;

class Articles_Row extends Row
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
            return ($absolute ? HOST : '/').Articles::PREVIEW_CAT_PATH.$this->preview_filename;
        }

        return null;
    }
}