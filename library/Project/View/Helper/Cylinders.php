<?php
class Project_View_Helper_Cylinders
{
    public function cylinders($layout, $cylinders, $valve_per_cylinder = null)
    {
        if ($layout)
        {
            if ($cylinders)
                $result = $layout.$cylinders;
            else
                $result = $layout.'?';
        }
        else
        {
            if ($cylinders)
                $result = $cylinders;
            else
                $result = '';
        }
        if ($valve_per_cylinder)
            $result .= '/' . $valve_per_cylinder; 
        
        return $result;
    }
}