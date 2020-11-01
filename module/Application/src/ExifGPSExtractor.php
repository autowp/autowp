<?php

namespace Application;

use function count;
use function explode;
use function floatval;
use function is_array;

class ExifGPSExtractor
{
    /**
     * @param mixed $exif
     */
    public function extract($exif): ?array
    {
        if (! is_array($exif)) {
            return null;
        }

        if (! isset($exif['GPS'])) {
            return null;
        }

        $gps = $exif['GPS'];

        if (! isset($gps["GPSLongitude"], $gps['GPSLongitudeRef'], $gps["GPSLatitude"], $gps['GPSLatitudeRef'])) {
            return null;
        }

        $lng = $this->getGps($gps["GPSLongitude"], $gps['GPSLongitudeRef']);
        $lat = $this->getGps($gps["GPSLatitude"], $gps['GPSLatitudeRef']);

        if (! $lng || ! $lat) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function getGps(array $exifCoord, string $hemi): float
    {
        $degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;

        $flip = $hemi === 'W' || $hemi === 'S' ? -1 : 1;

        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    private function gps2Num(string $coordPart): float
    {
        $parts = explode('/', $coordPart);

        if (count($parts) === 1) {
            return (float) $parts[0];
        }

        return floatval($parts[0]) / floatval($parts[1]);
    }
}
