<?php
declare(strict_types=1);

use Twig\TwigFilter;

final class EmojiExtension extends Twig\Extension\AbstractExtension
{
    /**
     * Gets filters
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('emoji', [$this, 'emoji']),
        );
    }
    
    public function getName()
    {
        return 'emoji';
    }
    
    public function emoji(string $body): string
    {
        global $config;
        
        if ($config['emojis_enabled'] === false) {
            return $body;
        }
        
        $matches = [];
        
        $emojis = array_reduce($config['emojis'], function ($carry, $item) {
            $emoji = str_replace(':', '', $item['emoji']);
            $carry[$emoji] = $item;
            return $carry;
        }, []);
        
        preg_match_all('/:([a-zA-Z0-9_+-]+):/', $body, $matches);
        foreach ($matches[1] as $match) {
            if (array_key_exists($match, $emojis)) {
                $emoji = $emojis[$match];
                $body = str_replace(
                    ':' . $match . ':',
                    sprintf(
                        '<img src="%s" title="%s" alt="%s" class="emoji emoji-%s">',
                        $emoji['url'],
                        $emoji['label'],
                        $emoji['label'],
                        $match
                    ),
                    $body
                );
            }
        }
        
        return $body;
    }
}
