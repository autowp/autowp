<?php

class Project_WheelSize
{
    protected $width = null;
    protected $series = null;
    protected $radius = null;
    protected $rimWidth = null;

    public function __construct($width = null, $series = null, $radius = null, $rimWidth = null)
    {
        $width = (int)$width;
        $series = (int)$series;
        $radius = (float)$radius;
        $rimWidth = (float)$rimWidth;

        $this->width = $width ? $width : null;
        $this->series = $series ? $series : null;
        $this->radius = $radius ? $radius : null;
        $this->rimWidth = $rimWidth ? $rimWidth : null;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getSeries()
    {
        return $this->series;
    }

    public function getRadius()
    {
        return $this->radius;
    }

    public function getRimWidth()
    {
        return $this->rimWidth;
    }

    public function getTyreName()
    {
        if ($this->width || $this->series || $this->radius) {
            $width = $this->width ? $this->width : '???';
            $series = $this->series ? $this->series : '??';
            $radius = $this->radius ? $this->radius : '??';

            return $width.'/'.$series.' R'.$radius;
        }

        return null;
    }

    public function getDiskName()
    {
        if ($this->rimWidth || $this->radius) {
            $rimWidth = $this->rimWidth ? $this->rimWidth : '?';
            $radius = $this->radius ? $this->radius : '??';

            return $rimWidth.'J × '.$radius;
        }

        return null;
    }
}