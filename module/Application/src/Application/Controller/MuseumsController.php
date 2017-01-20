<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;

use geoPHP;

class MuseumsController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toUrl('/map');
    }

    public function museumAction()
    {
        $table = new DbTable\Item();

        $museum = $table->fetchRow([
            'id = ?'           => (int)$this->params()->fromRoute('id'),
            'item_type_id = ?' => DbTable\Item\Type::MUSEUM
        ]);
        if (! $museum) {
            return $this->notFoundAction();
        }

        $itemPointTable = new DbTable\Item\Point();
        $itemPointRow = $itemPointTable->fetchRow([
            'item_id = ?' => $museum->id
        ]);
        
        $point = null;
        if ($itemPointRow && $itemPointRow->point) {
            $point = geoPHP::load(substr($itemPointRow->point, 4), 'wkb');
        }
        
        $linkTable = new DbTable\BrandLink();
        $links = $linkTable->fetchAll(
            $linkTable->select()
                ->where('item_id = ?', $museum['id'])
        );
        
        $itemLanguageTable = new DbTable\Item\Language();
        $db = $itemLanguageTable->getAdapter();
        $orderExpr = $db->quoteInto('language = ? desc', $this->language());
        $itemLanguageRows = $itemLanguageTable->fetchAll([
            'item_id = ?' => $museum['id']
        ], new \Zend_Db_Expr($orderExpr));
        
        $textIds = [];
        foreach ($itemLanguageRows as $itemLanguageRow) {
            if ($itemLanguageRow->text_id) {
                $textIds[] = $itemLanguageRow->text_id;
            }
        }
        
        $description = null;
        if ($textIds) {
            $description = $this->textStorage->getFirstText($textIds);
        }
        
        $pictureTable = new DbTable\Picture();
        
        $select = $pictureTable->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $museum->id)
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);
        
        $pictures = $this->pic()->listData($select, [
            'width' => 4
        ]);

        return [
            'museum'      => $museum,
            'point'       => $point,
            'links'       => $links,
            'description' => $description,
            'pictures'    => $pictures,
        ];
    }
}
