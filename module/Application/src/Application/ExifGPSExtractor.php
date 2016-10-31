<?php 

namespace Application;

class ExifGPSExtractor
{
    public function extract($exif)
    {
        if (!is_array($exif)) {
            return false;
        }
        
        if (!isset($exif['GPS'])) {
            return false;
        }
        
        $gps = $exif['GPS'];
        
        if (!isset($gps["GPSLongitude"], $gps['GPSLongitudeRef'], $gps["GPSLatitude"], $gps['GPSLatitudeRef'])) {
            return false;
        }
        
        $lng = $this->getGps($gps["GPSLongitude"], $gps['GPSLongitudeRef']);
        $lat = $this->getGps($gps["GPSLatitude"], $gps['GPSLatitudeRef']);
        
        if ($lng === false || $lat === false) {
            return false;
        }
        
        return [
            'lat' => $lat,
            'lng' => $lng
        ];
    }
    
    private function getGps($exifCoord, $hemi) {
    
        $degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;
    
        $flip = ($hemi == 'W' || $hemi == 'S') ? -1 : 1;
    
        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }
    
    private function gps2Num($coordPart) {
    
        $parts = explode('/', $coordPart);
    
        if (count($parts) <= 0) {
            return null;
        }
    
        if (count($parts) == 1) {
            return $parts[0];
        }

        return floatval($parts[0]) / floatval($parts[1]);
    }
}