<?php

class Project_View_Helper_Picture extends Zend_View_Helper_Abstract
{
    public function picture(Pictures_Row $picture = null, array $options = array())
    {
        $behaviour = isset($options['behaviour']) && $options['behaviour'];
        if ($behaviour) {
            $options['link'] = true;
            $options['border'] = true;
        }
        
        $view = $this->view;
        
        if (!$picture)
            return '<div style="margin:0;width:160px;height:20px;border:1px #cccccc solid;text-align:center;vertical-align:middle;padding:50px 0;cursor:help" title="Нет обработанной модератором фотографии в соответствующем ракурсе">НЕТ ФОТО</div>';
            
        /*$pictureUrl = $view->url(array(
            'module'        => 'default',
            'controller'    => 'picture',
            'action'        => 'index',
            'picture_id' => $picture->id
        ), 'picture', true, true);*/
            
        $pictureUrl = $view->pic($picture)->url();
        
        $caption = $picture->getCaption();
        $escapedCaption = $view->escape($caption);
        
        $style = 'width:160px;height:120px;';
        if (isset($options['border']) && $options['border'])
            $style .= 'border:1px #555555 solid;';

        $html = (string)$this->view->image($picture, 'file_name', array(
            'format' => 6,
            'alt'    => $caption,
            'title'  => $caption,
            'style'  => $style
        ));
        
        if (isset($options['link']) && $options['link']) {
            $html = $view->htmlA($pictureUrl, $html, false);
        }
        
        if ($behaviour) {
            $html = '<table cellpadding="0" cellspacing="0" align="center" summary="" class="pictureListPreview" style="width:168px"><tbody>' .
                        '<tr style="height:1px"><td class="corner"></td><td style="width:166px" class="line"></td><td class="corner"></td></tr>' .
                        '<tr>' . 
                            '<td class="vline"></td>'.
                            '<th style="width:158px">'.
                                $view->htmlA($pictureUrl, $escapedCaption, false) .
                            '</th>'.
                            '<td class="vline"></td>' . 
                        '</tr>' .
                        '<tr>' .
                            '<td class="vline"></td>'.
                            '<td class="Thumb" style="width:166px">'.
                                $html .
                            '</td>'.
                            '<td class="vline"></td>' .
                        '</tr>' .
                        '<tr>' . 
                            '<td class="vline"></td>'.
                            '<td>' . $view->pictureBehaviour($picture) . '</td>'.
                            '<td class="vline"></td>' . 
                        '</tr>' .
                        '<tr style="height:1px"><td class="corner"></td><td style="width:166px" class="line"></td><td class="corner"></td></tr>' .
                        '<tr><td style="height:10px" colspan="4"></td></tr>' . 
                    '</tbody></table>'; 
        }
        
        return $html;
    }
}