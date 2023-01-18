<?php

use Twig\TwigFilter;

class CssCompressTwigExtension extends Twig\Extension\AbstractExtension
{
    
    
    /**
     * Gets filters
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('ccss', [$this, 'cssComp']),
        );
    }
    
    public function getName()
    {
        return 'ccss';
    }
    
    public function cssComp(?string $filepath): string
    {
        global $config;
        
        if(null === $filepath) {
            return '';
        }
        
        if($config['minify_css'] === false) {
            return $filepath;
        }
        
        $root = realpath(__DIR__ . '/../../../../..');
    
        $path_parts = pathinfo($filepath);
        $newPath = $path_parts['dirname'] . DIRECTORY_SEPARATOR . str_replace(
                $path_parts['extension'],
                'min.' . $path_parts['extension'],
                $path_parts['basename']
            );
        
        if (file_exists($root . $newPath)) {
            if(!str_contains($newPath, '?v=')) {
                $newPath .= '?v=' . md5_file($root . $newPath);
            }
            
            return $newPath;
        }
        
        return $filepath;
    }
    
}
