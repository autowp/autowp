<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\ItemNameFormatter;

use Zend_Db_Expr;

class ItemsController extends AbstractRestfulController
{
    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;
    
    public function __construct(ItemNameFormatter $itemNameFormatter)
    {
        $this->itemNameFormatter = $itemNameFormatter;
    }
    
    public function alphaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $itemTable = $this->catalogue()->getItemTable();
        $carAdapter = $itemTable->getAdapter();
        $chars = $carAdapter->fetchCol(
            $carAdapter->select()
                ->distinct()
                ->from('item', ['char' => new Zend_Db_Expr('UPPER(LEFT(name, 1))')])
                ->order('char')
        );
        
        $groups = [
            'numbers' => [],
            'english' => [],
            'other'   => []
        ];
        
        foreach ($chars as $char) {
            if (preg_match('|^["0-9-]$|isu', $char)) {
                $groups['numbers'][] = $char;
            } elseif (preg_match('|^[A-Za-z]$|isu', $char)) {
                $groups['english'][] = $char;
            } else {
                $groups['other'][] = $char;
            }
        }
        
        return new JsonModel([
            'groups' => $groups
        ]);
    }
    
    public function alphaItemsAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $items = [];
        $char = null;
        
        $c = $this->params()->fromQuery('char');
        $char = mb_substr($c, 0, 1);
        
        if ($char) {
            $char = $char;
            
            $itemTable = $this->catalogue()->getItemTable();
            $rows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('name LIKE ?', $char.'%')
                    ->order(['name', 'begin_year', 'end_year'])
            );
            
            $language = $this->language();
            
            foreach ($rows as $row) {
                $items[] = [
                    'name' => $this->itemNameFormatter->format(
                        $row->getNameData($language), 
                        $language
                    ),
                    'url'  => $this->url()->fromRoute('moder/cars/params', [
                        'action'  => 'car',
                        'item_id' => $row->id
                    ], [], true)
                ]; 
            }
        }
        
        return new JsonModel([
            'items' => $items
        ]);
    }
}
