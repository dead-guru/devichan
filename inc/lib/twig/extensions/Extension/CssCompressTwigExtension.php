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
    
    public function cssComp(string $filepath): string
    {
        global $config;

        if ($filepath === '') {
            return $filepath;
        }

        $root = realpath(__DIR__ . '/../../../../..');

        if ($config['minify_css']) {
            $path_parts = pathinfo($filepath);
            $newPath = $path_parts['dirname'] . DIRECTORY_SEPARATOR . str_replace(
                    $path_parts['extension'],
                    'min.' . $path_parts['extension'],
                    $path_parts['basename']
                );
            if (is_file($root . $newPath)) {
                $filepath = $newPath;
            }
        }

        // stylesheets are cached as immutable, so the URL has to change with the file
        return is_file($root . $filepath) ? $filepath . '?v=' . md5_file($root . $filepath) : $filepath;
    }
    
}
