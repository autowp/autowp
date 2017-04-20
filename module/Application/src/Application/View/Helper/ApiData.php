<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Hydrator\Api\RestHydrator;

class ApiData extends AbstractHelper
{
    /**
     * @var RestHydrator
     */
    private $userHydrator;
    
    public function __construct(RestHydrator $userHydrator)
    {
        $this->userHydrator = $userHydrator;
    }

    public function __invoke()
    {
        $language = $this->view->language();
        
        $languages = [];
        foreach ($this->view->languagePicker() as $item) {
            $active = $item['language'] == $language;
            $languages[] = [
                'url'    => $item['url'],
                'name'   => $item['name'],
                'flag'   => $item['flag'],
                'active' => $active
            ];
            if (! $active){
                $this->view->headLink([
                    'rel'      => 'alternate',
                    'href'     => $item['url'],
                    'hreflang' => $item['language']
                ]);
            }
        }
        
        $moderMenu = null;
        if ($this->view->user()->inheritsRole('moder')) {
            $moderMenu = $this->view->moderMenu(true);
        }
        
        $user = $this->view->user()->get();
        $userData = null;
        if ($user) {
            $this->userHydrator->setOptions([
                'language' => $language,
                'fields'   => [],
                'user_id'  => $user['id']
            ]);
            $userData = $this->userHydrator->extract($user);
        }
        
        return [
            'languages'  => $languages,
            'isModer'    => $this->view->user()->inheritsRole('moder'),
            'mainMenu'   => $this->view->mainMenu(true),
            'moderMenu'  => $moderMenu,
            'sidebar'    => $this->view->sidebar(true),
            'user'       => $userData
        ];
    }
}
