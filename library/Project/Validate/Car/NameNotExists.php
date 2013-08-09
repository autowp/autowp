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
            if ($by <= 0)
                $by = null;


            $select = $cars->select()
                ->where('caption = ?', $value)
                ->where('body = ?', $body);

            if (is_null($by)) {
                $select->where('begin_year IS NULL');
            } else {
                $select->where('begin_year = ?', $by);
            }

            $row = $cars->fetchAll($select)->current();
            if ($row) {
                $this->_messages[] = sprintf("Автомобиль с названием '%s', номером кузова '%s' и годом началом выпуска '%s' уже существует", $row->caption, $row->body, $row->begin_year);
                return false;
            }
        }

        return true;
    }
}