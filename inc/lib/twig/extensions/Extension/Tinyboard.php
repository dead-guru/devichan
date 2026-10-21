<?php

class Twig_Extensions_Extension_Tinyboard extends Twig\Extension\AbstractExtension
{
	/**
	* Returns a list of filters to add to the existing list.
	*
	* @return array An array of filters
	*/
	public function getFilters()
	{
		return array(
			new \Twig\TwigFilter('filesize', 'format_bytes'),
			new \Twig\TwigFilter('truncate', 'twig_truncate_filter'),
			new \Twig\TwigFilter('truncate_body', 'truncate'),
			new \Twig\TwigFilter('truncate_filename', 'twig_filename_truncate_filter'),
			new \Twig\TwigFilter('extension', 'twig_extension_filter'),
			new \Twig\TwigFilter('sprintf', 'sprintf'),
			new \Twig\TwigFilter('capcode', 'capcode'),
			new \Twig\TwigFilter('remove_modifiers', 'remove_modifiers'),
			new \Twig\TwigFilter('hasPermission', 'twig_hasPermission_filter'),
			new \Twig\TwigFilter('date', 'twig_date_filter'),
			new \Twig\TwigFilter('poster_id', 'poster_id'),
			new \Twig\TwigFilter('remove_whitespace', 'twig_remove_whitespace_filter'),
			new \Twig\TwigFilter('count', 'count'),
			new \Twig\TwigFilter('ago', 'ago'),
			new \Twig\TwigFilter('until', 'until'),
			new \Twig\TwigFilter('push', 'twig_push_filter'),
			new \Twig\TwigFilter('bidi_cleanup', 'bidi_cleanup'),
			new \Twig\TwigFilter('addslashes', 'addslashes'),
			new \Twig\TwigFilter('cloak_ip', 'cloak_ip'),
			new \Twig\TwigFilter('cloak_mask', 'cloak_mask'),
		);
	}
	
	/**
	* Returns a list of functions to add to the existing list.
	*
	* @return array An array of filters
	*/
	public function getFunctions()
	{
		return array(
			new \Twig\TwigFunction('time', 'time'),
			new \Twig\TwigFunction('floor', 'floor'),
			new \Twig\TwigFunction('timezone', 'twig_timezone_function'),
			new \Twig\TwigFunction('hiddenInputs', 'hiddenInputs'),
			new \Twig\TwigFunction('hiddenInputsHash', 'hiddenInputsHash'),
			new \Twig\TwigFunction('ratio', 'twig_ratio_function'),
			new \Twig\TwigFunction('secure_link_confirm', 'twig_secure_link_confirm'),
			new \Twig\TwigFunction('secure_link', 'twig_secure_link'),
			new \Twig\TwigFunction('link_for', 'link_for')
		);
	}
	
	/**
	* Returns the name of the extension.
	*
	* @return string The extension name
	*/
	public function getName()
	{
		return 'tinyboard';
	}
}

function twig_timezone_function() {
	return 'Z';
}

function twig_push_filter($array, $value) {
	array_push($array, $value);
	return $array;
}

function twig_remove_whitespace_filter($data) {
	return preg_replace('/[\t\r\n]/', '', $data);
}

function twig_date_filter($date, $format) {
	$locale = setlocale(LC_TIME, '0');
	if (preg_match('/^(?:C|POSIX)(?:\.|$)/i', $locale))
		$locale = 'en_US_POSIX';
	$date = (new DateTimeImmutable('@' . (int)$date))->setTimezone(new DateTimeZone('GMT'));
	$expand = function ($format) use (&$expand, $date) {
		return preg_replace_callback('/%%|%([EO]?)([a-zA-Z])/', function ($match) use (&$expand, $date) {
			if ($match[0] === '%%') return '%%';
			[, $modifier, $code] = $match;
			if ($modifier === 'O') {
				return nl_langinfo(ALT_DIGITS) === '' ? '%' . $code : $match[0];
			}
			$composites = ['c' => [D_T_FMT, ERA_D_T_FMT], 'x' => [D_FMT, ERA_D_FMT], 'X' => [T_FMT, ERA_T_FMT]];
			if (isset($composites[$code])) {
				[$normal, $era] = $composites[$code];
				$pattern = $modifier === 'E' ? nl_langinfo($era) : '';
				$pattern = $expand($pattern ?: nl_langinfo($normal));
				// ICU can render these locales, but the library cannot expand their era/digit modifiers.
				return preg_match('/%[EO][a-zA-Z]/', $pattern) ? '%' . $code : $pattern;
			}
			if ($modifier === 'E') {
				return nl_langinfo(ERA) === '' ? '%' . $code : $match[0];
			}
			if ($code === 'r') return $expand(nl_langinfo(T_FMT_AMPM) ?: '%I:%M:%S %p');
			if ($code === 'D') return '%m/%d/%y';
			if ($code === 'a' || $code === 'A') {
				return nl_langinfo(($code === 'a' ? ABDAY_1 : DAY_1) + (int)$date->format('w'));
			}
			if ($code === 'b' || $code === 'B' || $code === 'h') {
				return nl_langinfo(($code === 'B' ? MON_1 : ABMON_1) + (int)$date->format('n') - 1);
			}
			if ($code === 'p' || $code === 'P') {
				$period = nl_langinfo((int)$date->format('G') < 12 ? AM_STR : PM_STR);
				return str_replace('%', '%%', $code === 'P' ? mb_strtolower($period) : $period);
			}
			return $match[0];
		}, $format);
	};
	return \PHP81_BC\strftime($expand($format), $date, $locale);
}

function twig_hasPermission_filter($mod, $permission, $board = null) {
	return hasPermission($permission, $board, $mod);
}

function twig_extension_filter($value, $case_insensitive = true) {
	$value ??= '';
	$ext = mb_substr($value, mb_strrpos($value, '.') + 1);
	if($case_insensitive)
		$ext = mb_strtolower($ext);
	return $ext;
}

function twig_sprintf_filter( $value, $var) {
	return sprintf($value, $var);
}

function twig_truncate_filter($value, $length = 30, $preserve = false, $separator = '…') {
	if (mb_strlen($value) > $length) {
		if ($preserve) {
			if (false !== ($breakpoint = mb_strpos($value, ' ', $length))) {
				$length = $breakpoint;
			}
		}
		return mb_substr($value, 0, $length) . $separator;
	}
	return $value;
}

function twig_filename_truncate_filter($value, $length = 30, $separator = '…') {
	if (mb_strlen($value) > $length) {
		$value = strrev($value);
		$array = array_reverse(explode(".", $value, 2));
		$array = array_map("strrev", $array);
		
		$filename = &$array[0];
		$extension = isset($array[1]) ? $array[1] : false;

		$filename = mb_substr($filename, 0, $length - ($extension ? mb_strlen($extension) + 1 : 0)) . $separator;

		return implode(".", $array);
	}
	return $value;
}

function twig_ratio_function($w, $h) {
	return fraction($w, $h, ':');
}
function twig_secure_link_confirm($text, $title, $confirm_message, $href) {
	global $config;

	return '<a onclick="if (event.which==2) return true;if (confirm(\'' . htmlentities(addslashes($confirm_message)) . '\')) document.location=\'?/' . htmlspecialchars(addslashes($href . '/' . make_secure_link_token($href))) . '\';return false;" title="' . htmlentities($title) . '" href="?/' . $href . '">' . $text . '</a>';
}
function twig_secure_link($href) {
	return $href . '/' . make_secure_link_token($href);
}
