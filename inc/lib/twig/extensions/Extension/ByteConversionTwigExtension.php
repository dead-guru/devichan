<?php

use Twig\TwigFilter;

class ByteConversionTwigExtension extends Twig\Extension\AbstractExtension
{
    
    
    /**
     * Gets filters
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('format_bytes', [$this, 'formatBytes']),
        );
    }
    
    public function getName()
    {
        return 'format_bytes';
    }
    
    /**
     * @param $bytes
     * @param int $precision
     * @return string
     */
    public function formatBytes($bytes, $precision = 2)
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$precision}f", $bytes / (1024 ** $factor)) . @$size[$factor];
    }
    
}
