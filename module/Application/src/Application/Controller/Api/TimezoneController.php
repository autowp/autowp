<?php

namespace Application\Controller\Api;

use DateTimeZone;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

use function array_unique;
use function sort;

use const SORT_STRING;

class TimezoneController extends AbstractRestfulController
{
    public function listAction()
    {
        $list = [];
        foreach (DateTimeZone::listAbbreviations() as $group) {
            foreach ($group as $timeZone) {
                $tzId = $timeZone['timezone_id'];
                if ($tzId) {
                    $list[] = $tzId;
                }
            }
        }
        $list = array_unique($list, SORT_STRING);
        sort($list, SORT_STRING);

        return new JsonModel([
            'items' => $list,
        ]);
    }
}
