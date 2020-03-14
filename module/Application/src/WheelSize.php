<?php

namespace Application;

class WheelSize
{
    protected ?int $width;
    protected ?int $series;
    protected ?float $radius;
    protected ?float $rimWidth;

    public function __construct(?int $width, ?int $series, ?float $radius, ?float $rimWidth)
    {
        $width    = (int) $width;
        $series   = (int) $series;
        $radius   = (float) $radius;
        $rimWidth = (float) $rimWidth;

        $this->width    = $width ? $width : null;
        $this->series   = $series ? $series : null;
        $this->radius   = $radius ? $radius : null;
        $this->rimWidth = $rimWidth ? $rimWidth : null;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getSeries(): ?int
    {
        return $this->series;
    }

    public function getRadius(): ?float
    {
        return $this->radius;
    }

    public function getRimWidth(): ?float
    {
        return $this->rimWidth;
    }

    public function getTyreName(): ?string
    {
        if ($this->width || $this->series || $this->radius) {
            $width  = $this->width ? $this->width : '???';
            $series = $this->series ? $this->series : '??';
            $radius = $this->radius ? $this->radius : '??';

            return $width . '/' . $series . ' R' . $radius;
        }

        return null;
    }

    public function getDiskName(): ?string
    {
        if ($this->rimWidth || $this->radius) {
            $rimWidth = $this->rimWidth ? $this->rimWidth : '?';
            $radius   = $this->radius ? $this->radius : '??';

            return $rimWidth . 'J × ' . $radius;
        }

        return null;
    }
}
