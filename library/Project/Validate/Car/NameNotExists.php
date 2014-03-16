<?php

class Project_Validate_Car_NameNotExists extends Zend_Validate_Abstract
{
    const ALREADY_EXISTS = 'alreadyExists';

    public function isValid($value, $context = null)
    {
        $this->_messages = array();

        $cars = new Cars();

        if (is_array($context)) {
            $body = isset($context['body']) ? (string)$context['body'] : '';
            $by = isset($context['begin_year']) ? (int)$context['begin_year'] : 0;
            if ($by <= 0) {
                $by = null;
            }
            $ey = isset($context['end_year']) ? (int)$context['end_year'] : 0;
            if ($ey <= 0) {
                $ey = null;
            }
            $isGroup = (bool)isset($context['is_group']) && $context['is_group'];

            $select = $cars->select()
                ->where('caption = ?', $value)
                ->where('body = ?', $body);

            if (is_null($by)) {
                $select->where('begin_year IS NULL');
            } else {
                $select->where('begin_year = ?', $by);
            }

            if (is_null($ey)) {
                $select->where('end_year IS NULL');
            } else {
                $select->where('end_year = ?', $ey);
            }

            if ($isGroup) {
                $select->where('is_group');
            } else {
                $select->where('not is_group');
            }

            $row = $cars->fetchAll($select)->current();
            if ($row) {
                $this->_messages[] = sprintf("Автомобиль с названием '%s', номером кузова '%s', годом началом выпуска '%s' и годом окончания выпуска '%s' уже существует", $row->caption, $row->body, $row->begin_year, $row->end_year);
                return false;
            }
        }

        return true;
    }
}