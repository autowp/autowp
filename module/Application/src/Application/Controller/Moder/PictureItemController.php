<?php 

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\DbTable\User;
use Autowp\User\Model\DbTable\User\Row as UserRow;

use Application\Form\Moder\Inbox as InboxForm;
use Application\HostManager;
use Application\Model\Brand as BrandModel;
use Application\Model\Comments;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Comment\Topic as CommentTopic;
use Application\Model\DbTable\Engine;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Perspective;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\ModerVote as PictureModerVote;
use Application\Model\DbTable\Picture\Row as PictureRow;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\Message;
use Application\Model\PictureItem;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\PictureNameFormatter;
use Application\Service\TelegramService;

use Exception;

use Zend_Db_Expr;
use Zend_Db_Table_Rowset;

class PictureItemController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $pictureTable;
    
    /**
     * @var PictureItem
     */
    private $pictureItem;
    
    /**
     * @var Vehicle
     */
    private $itemTable;
    
    public function __construct(
        Picture $pictureTable,
        PictureItem $pictureItem
    ) {
        $this->pictureItem = $pictureItem;
        $this->pictureTable = $pictureTable;
        $this->itemTable = new Vehicle();
    }
    
    private function getPictureUrl(PictureRow $picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('moder/pictures/params', [
            'action'     => 'picture',
            'picture_id' => $picture->id
        ], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]);
    }
    
    public function removeAction()
    {
        $canMove = $this->user()->isAllowed('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }
    
        $picture = $this->pictureTable->find($this->params('picture_id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }
    
        $item = $this->itemTable->find($this->params('item_id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }
    
        $this->pictureItem->remove($picture->id, $item->id);
    
        $this->pictureTable->refreshPictureCounts($this->pictureItem, $picture);
    
        return $this->redirect()->toUrl($this->getPictureUrl($picture));
    }
    
    public function saveAreaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
    
        $picture = $this->pictureTable->find($this->params('picture_id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }
        
        $item = $this->itemTable->find($this->params('item_id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }
    
        $left = round($this->params()->fromPost('x'));
        $top = round($this->params()->fromPost('y'));
        $width = round($this->params()->fromPost('w'));
        $height = round($this->params()->fromPost('h'));
    
        $left = max(0, $left);
        $left = min($picture->width, $left);
        $width = max(1, $width);
        $width = min($picture->width, $width);
    
        $top = max(0, $top);
        $top = min($picture->height, $top);
        $height = max(1, $height);
        $height = min($picture->height, $height);
    
        if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
            $area =  [
                'left'   => $left,
                'top'    => $top,
                'width'  => $width,
                'height' => $height
            ];
        } else {
            $area =  [
                'left'   => null,
                'top'    => null,
                'width'  => null,
                'height' => null
            ];
        }
        $this->pictureItem->setProperties($picture->id, $item->id, [
            'area' => $area
        ]);
    
        $this->log(sprintf(
            'Выделение области на картинке %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [$picture]);
    
        return new JsonModel([
            'ok' => true
        ]);
    }
}