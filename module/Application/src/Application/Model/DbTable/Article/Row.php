<?php

namespace Application\Model\DbTable\Article;

class Row extends \Autowp\Commons\Db\Table\Row
{
    public function previewExists()
    {
        return $this->preview_width && $this->preview_height && strlen($this->preview_filename);
    }
}
