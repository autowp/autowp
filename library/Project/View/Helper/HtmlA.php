<?php

class Project_View_Helper_HtmlA extends Zend_View_Helper_HtmlElement
{
	public function htmlA($attribs, $content, $escape = true)
	{
		if ($escape) {
			$content = $this->view->escape($content);
		}
			
		if (!is_array($attribs))
			$attribs = array('href' => $attribs);

		if (isset($attribs['shuffle']) && $attribs['shuffle']) {
			unset($attribs['shuffle']);
			$attribs = $this->_shuffleAttribs($attribs);
		}
		
		foreach ($attribs as $key => $value) {
			if (!isset($value)) {
				unset($attribs[$key]);
			}
		}

		return '<a' . $this->_htmlAttribs($attribs) . '>' . $content . '</a>';
	}
	
	public function url($attribs)
	{
		if (!is_array($attribs)) {
			$attribs = array('href' => $attribs);
		}
		
		$href = isset($attribs['href']) ? $attribs['href'] : '';
		
		$title = $href;
		if ($href) {
			$pu = parse_url($href);
			
			$title = (isset($pu['host']) ? $pu['host'] : '');
			$title = preg_replace('|^www\.|isu', '', $title);
		}
		
		return $this->htmlA($attribs, $title, true);
	}
	
	protected function _shuffleAttribs($attribs)
	{
		$keys = array_keys($attribs);
		shuffle($keys);
		return array_merge(array_flip($keys), $attribs); 
	}
}