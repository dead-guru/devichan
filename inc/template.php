<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */
require_once 'inc/bootstrap.php';
defined('TINYBOARD') or exit;

/** @var \Twig\Environment $twig */
$twig = false;

function load_twig() {
	global $twig, $config;

	$cache_dir = "{$config['dir']['template']}/cache";

	$loader = new \Twig\Loader\FilesystemLoader($config['dir']['template']);
	$loader->setPaths($config['dir']['template']);
	$twig = new Twig\Environment($loader, array(
		'autoescape' => false,
		'cache' => is_writable('templates/') || (is_dir($cache_dir) && is_writable($cache_dir)) ?
			new Vichan\Twig\FilesystemCache\TinyboardFilesystem($cache_dir) : false,
		'debug' => $config['debug'],
	));
	$twig->addExtension(new Twig_Extensions_Extension_Tinyboard());
	$twig->addExtension(new I18nExtension());
	$twig->addExtension(new ByteConversionTwigExtension());
	$twig->addExtension(new CssCompressTwigExtension());
	$twig->addExtension(new EmojiExtension());
}

function Element($templateFile, array $options) {
	global $config, $debug, $twig, $build_pages;
	
	if (!$twig)
		load_twig();
  
	if (function_exists('create_pm_header') && ((isset($options['mod']) && $options['mod']) || isset($options['__mod'])) && !preg_match('!^mod/!', $templateFile)) {
		$options['pm'] = create_pm_header();
	}
	
	if (isset($options['body']) && $config['debug']) {
		$_debug = $debug;
		
		if (isset($debug['start'])) {
			$_debug['time']['total'] = '~' . round((microtime(true) - $_debug['start']) * 1000, 2) . 'ms';
			$_debug['time']['init'] = '~' . round(($_debug['start_debug'] - $_debug['start']) * 1000, 2) . 'ms';
			unset($_debug['start']);
			unset($_debug['start_debug']);
		}
		if ($config['try_smarter'] && isset($build_pages) && !empty($build_pages))
			$_debug['build_pages'] = $build_pages;
		$_debug['included'] = get_included_files();
		$_debug['memory'] = round(memory_get_usage(true) / (1024 * 1024), 2) . ' MiB';
		$_debug['time']['db_queries'] = '~' . round($_debug['time']['db_queries'] ?? 0 * 1000, 2) . 'ms';
		$_debug['time']['exec'] = '~' . round($_debug['time']['exec'] ?? 0 * 1000, 2) . 'ms';
		$options['body'] .=
			'<h3>Debug</h3><pre style="white-space: pre-wrap;font-size: 10px;">' .
				str_replace("\n", '<br/>', utf8tohtml(print_r($_debug, true))) .
			'</pre>';
	}
	
	// Read the template file
	if (@file_get_contents("{$config['dir']['template']}/{$templateFile}")) {
        $body = $twig->render($templateFile, $options);
        
        if ($config['minify_html'] && preg_match('/\.html$/', $templateFile)) {
			$body = trim(preg_replace("/[\t\r\n]/", '', $body));
		}
		
		return $body;
	} else {
		throw new Exception(sprintf("Template file '%s' does not exist or is empty in '%s'!", $templateFile, $config['dir']['template']));
	}
}

