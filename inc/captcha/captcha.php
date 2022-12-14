<?php

class CzaksCaptcha
{
    
    private int $width;
    
    private int $height;
    
    private array $charset;
    
    private string $text;
    
    public function __construct(string $text, int $width, int $height, array|string|bool $charset = false)
    {
        if (!$charset) {
            $charset = 'abcdefghijklmnopqrstuvwxyz';
        }
        
        //$charset = implode('', $charset);
        
        $this->text = $text;
        $this->width = $width;
        $this->height = $height;
        $this->charset = preg_split('//u', $charset);
    }
    
    
    public function to_html(&$a = false): string
    {
        $base64Image = $this->generate($this->text, $this->width, $this->height);
        
        return '<img src="data:image/jpg;base64,' . $base64Image . '" title="Click for regenerate" alt="captcha">';
    }
    
    private function rand_color()
    {
        return sprintf('#%06X', random_int(0, 0xFFFFFF));
    }
    
    
    private function generate(string $string, int $width, int $height): string
    {
        $Imagick = new Imagick();
        $bg = new ImagickPixel('#EEEEEE');
        $ImagickDraw = new ImagickDraw();
        //$ImagickDraw->setFont();
        $ImagickDraw->setFontSize(50);
        
        $Imagick->newImage($width, $height, $bg);
        
        for ($x = 0; $x <= 40; $x++) {
            $ImagickDraw->setStrokeOpacity(random_int(3, 7) / 10);
            $ImagickDraw->setStrokeColor(new ImagickPixel($this->rand_color()));
            $ImagickDraw->setStrokeWidth(random_int(1, 3));
            $ImagickDraw->line(
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height)
            );
        }
        $ImagickDraw->setFillColor(new ImagickPixel('#000000'));
        $Imagick->annotateImage($ImagickDraw, 30, $height / 2, random_int(0, 10), $string);
        $Imagick->blurImage(0.4, 1);
        $Imagick->swirlImage(20);
        $Imagick->drawImage($ImagickDraw);
        $Imagick->setImageFormat('png');
        
        return base64_encode($Imagick->getImageBlob());
    }
    
}
