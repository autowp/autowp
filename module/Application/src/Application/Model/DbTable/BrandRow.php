<?php

namespace Application\Model\DbTable;

use Application\Db\Table\Row;
use Application\Model\DbTable\BrandLanguage;
use Application\Model\DbTable\Picture;

use Zend_Db_Expr;

class BrandRow extends Row
{
    /**
     * @var BrandLanguage
     */
    private $langTable;

    /**
     * @return BrandLanguage
     */
    private function getLanguageTable()
    {
        return $this->langTable
            ? $this->langTable
            : $this->langTable = new BrandLanguage();
    }

    public function getLanguageName($language)
    {
        $langRow = $this->getLanguageTable()->fetchRow([
            'brand_id = ?' => $this->id,
            'language = ?' => $language
        ]);

        return $langRow ? $langRow->name : $this->name;
    }

    public function getTotalPicturesCount()
    {
        return $this->carpictures_count + $this->enginepictures_count +
               $this->logopictures_count + $this->mixedpictures_count +
               $this->unsortedpictures_count;
    }

    public function refreshPicturesCount()
    {
        $this->refreshCarPicturesCount();
        $this->refreshLogoPicturesCount();
        $this->refreshMixedPicturesCount();
        $this->refreshUnsortedPicturesCount();
    }

    public function refreshCarPicturesCount()
    {
        $db = $this->getTable()->getAdapter();

        $this->carpictures_count = (int)$db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(pictures.id)'))
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.item_id', null)
                ->where('brand_item.brand_id = ?', $this->id)
        );
        $this->save();
    }

    public function refreshLogoPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->logopictures_count = (int)$db->fetchOne($sql, [
            $this->id, Picture::LOGO_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW]);
        $this->save();
    }

    public function refreshMixedPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->mixedpictures_count = (int)$db->fetchOne($sql, [
            $this->id, Picture::MIXED_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW]);
        $this->save();
    }

    public function refreshUnsortedPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->unsortedpictures_count = (int)$db->fetchOne($sql, [
            $this->id, Picture::UNSORTED_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW]);
        $this->save();
    }
}
