<?php 

namespace Project;

use Zend_View;

class UserText
{
    public function __construct(Zend_View $view)
    {
        
    }
    
    public function render($text)
    {
        $out = [];
        
        $regexp = '@(https?://[[:alnum:]:\.,/?&_=~+%#\'!|\(\)-]{3,})|(www\.[[:alnum:]\.,/?&_=~+%#\'!|\(\)-]{3,})@isu';
        while (preg_match($regexp, $text, $regs)) {
            if ($regs[1]) {
                $umatch = $regs[1];
                $url = $umatch;
            } else {
                $umatch = $regs[2];
                $url = 'http://'.$umatch;
            }
        
            $linkPos = mb_strpos($text, $umatch);
            if ($linkPos !== false) {
                
                
            } else {
                break;
            }
        }
        
        return implode($out);
    }
}