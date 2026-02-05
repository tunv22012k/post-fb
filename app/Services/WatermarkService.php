<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WatermarkService
{
    protected $manager;

    public function __construct()
    {
        // Use GD driver
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Add text watermark to an image.
     * 
     * @param string $imagePath Absolute path to the image
     * @param string $text Text to overlay
     * @return string Path to the watermarked image
     */
    public function addWatermark(string $imagePath, string $text): string
    {
        if (!file_exists($imagePath)) {
            return $imagePath; // Fail safe
        }

        $image = $this->manager->read($imagePath);

        // Add watermark text (bottom right)
        $image->text($text, 
            $image->width() - 20, 
            $image->height() - 20, 
            function ($font) {
                $font->size(24);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
                // $font->file(public_path('fonts/Roboto-Bold.ttf')); // Optional: custom font
            }
        );

        // Save as new file to avoid overwriting original immediately (optional)
        // For mass production, maybe overwrite or create 'processed' version.
        // Let's create a processed version.
        $pathInfo = pathinfo($imagePath);
        $newPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_w.' . $pathInfo['extension'];
        
        $image->save($newPath);

        return $newPath;
    }
}
