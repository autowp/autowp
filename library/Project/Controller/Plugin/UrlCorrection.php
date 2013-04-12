<?php 

class Project_Controller_Plugin_UrlCorrection extends Zend_Controller_Plugin_Abstract
{
    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     * 
     * @todo scheme preserve
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        if ($request instanceof Zend_Controller_Request_Http) {
            $uri = $request->getRequestUri();
            
            $method = $request->getMethod();
            
            if ($method == 'GET') {
            
                $filteredUri = preg_replace('|^/index\.php|isu', '', $uri);

                if ($filteredUri != $uri) {
                    $request->setDispatched(true);
            
                    $redirectUrl = $request->getScheme() . '://' . 
                        $request->getHttpHost() . $filteredUri;
            
                    $this->getResponse()->setRedirect($redirectUrl, 301);
                }
            }
        }
    }
}