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
        
        return '<img style="width: 50%" src="data:image/jpg;base64,' . $base64Image . '" title="Click for regenerate" alt="captcha">';
    }
    
    public function to_image(): string
    {
        return $this->generate($this->text, $this->width, $this->height);
    }
    
    private function rand_color()
    {
        return sprintf('#%06X', random_int(0, 0xFFFFFF));
    }
    
    
    private function generate(string $string, int $width, int $height): string
    {
        $locale = setlocale(LC_NUMERIC, '0');
        // Imagick drawing commands require decimal points.
        setlocale(LC_NUMERIC, 'C');
        try {
            $Imagick = new Imagick();
            $bg = new ImagickPixel('#EEEEEE');
            $ImagickDraw = new ImagickDraw();
            $ImagickDraw->setFont(__DIR__ . '/font.ttf');
            $ImagickDraw->setFontSize(65);

            $Imagick->newImage($width, $height, $bg);
            $Imagick->addNoiseImage($Imagick::NOISE_POISSON);

            // Leave room for rotation and swirl at the image edges.
            $metrics = $Imagick->queryFontMetrics($ImagickDraw, $string);
            $angle = deg2rad(10);
            $textHeight = $metrics['ascender'] - $metrics['descender'];
            $rotatedWidth = $metrics['textWidth'] * cos($angle) + $textHeight * sin($angle);
            $rotatedHeight = $metrics['textWidth'] * sin($angle) + $textHeight * cos($angle);
            $scale = min(1, ($width - 20) / $rotatedWidth, ($height - 20) / $rotatedHeight);
            $ImagickDraw->setFontSize(max(1, floor(65 * $scale)));
            $ImagickDraw->setGravity(Imagick::GRAVITY_CENTER);

            for ($x = 0; $x <= 15; $x++) {
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
            $ImagickDraw->setStrokeWidth(1);
            $ImagickDraw->setFillColor(new ImagickPixel('#000000'));
            $Imagick->annotateImage($ImagickDraw, 0, 0, random_int(0, 10), $string);
            $Imagick->blurImage(0.4, 1);
            $Imagick->swirlImage(20);
            $Imagick->drawImage($ImagickDraw);
            $Imagick->setImageFormat('png');

            return base64_encode($Imagick->getImageBlob());
        } finally {
            setlocale(LC_NUMERIC, $locale);
        }
    }
    
}
