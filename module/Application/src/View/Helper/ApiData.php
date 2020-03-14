<?php

namespace Application\View\Helper;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\MainMenu;
use Laminas\View\Helper\AbstractHelper;

class ApiData extends AbstractHelper
{
    private AbstractRestHydrator $userHydrator;

    private MainMenu $mainMenu;

    public function __construct(AbstractRestHydrator $userHydrator, MainMenu $mainMenu)
    {
        $this->userHydrator = $userHydrator;
        $this->mainMenu     = $mainMenu;
    }

    public function __invoke(): array
    {
        $language = $this->view->language();

        $languages = [];
        foreach ($this->view->languagePicker() as $item) {
            $active      = $item['language'] === $language;
            $languages[] = [
                'url'    => $item['url'],
                'name'   => $item['name'],
                'flag'   => $item['flag'],
                'active' => $active,
            ];
            if (! $active) {
                $this->view->headLink([
                    'rel'      => 'alternate',
                    'href'     => $item['url'],
                    'hreflang' => $item['language'],
                ]);
            }
        }

        $moderMenu = null;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($this->view->user()->inheritsRole('moder')) {
            $moderMenu = $this->view->moderMenu();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user     = $this->view->user()->get();
        $userData = null;
        if ($user) {
            $this->userHydrator->setOptions([
                'language' => $language,
                'fields'   => [],
                'user_id'  => $user['id'],
            ]);
            $userData = $this->userHydrator->extract($user);
        }

        return [
            'languages' => $languages,
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            'isModer'   => $this->view->user()->inheritsRole('moder'),
            'mainMenu'  => $this->mainMenu->getMenu(null, true),
            'moderMenu' => $moderMenu,
            'sidebar'   => $this->view->sidebar(true),
            'user'      => $userData,
        ];
    }
}
