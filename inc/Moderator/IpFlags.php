<?php

namespace DeVichan\Moderator;

use DeVichan\GeoIP;

final class IpFlags {
	public static function country(?string $ip): ?array {
		global $config, $mod;

		if (empty($mod['id']) || empty($config['mod']['country_flags']) || !$ip)
			return null;

		[$address, $prefix] = array_pad(explode('/', $ip, 2), 2, null);
		$packed = @inet_pton($address);
		if ($packed === false || ($prefix !== null && $prefix !== (string)(strlen($packed) * 8)))
			return null;

		$country = GeoIP::country($address);
		$default = $config['root'] . 'static/flags/%s.png';
		if ($country && ($config['uri_flags'] ?? $default) === $default && !is_file(dirname(__DIR__, 2) . '/static/flags/' . $country['code'] . '.png'))
			return null;
		return $country;
	}

	public static function render(?string $ip): string {
		global $config;

		$country = self::country($ip);
		if (!$country)
			return '';

		$uri = $config['uri_flags'] ?? $config['root'] . 'static/flags/%s.png';
		$src = htmlspecialchars(sprintf($uri, $country['code']), ENT_QUOTES, 'UTF-8');
		$name = htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8');
		return ' <img class="flag mod-ip-flag" src="' . $src . '" alt="' . $name . '" title="' . $name . '" width="16" height="11" style="vertical-align:middle" />';
	}

	public static function decorate(string $html, bool $public = false): string {
		global $config, $mod;

		if ($public || empty($mod['id']) || empty($config['mod']['country_flags']))
			return $html;

		$doc = new \DOMDocument();
		$errors = libxml_use_internal_errors(true);
		try {
			$doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($errors);
		}
		$root = $doc->documentElement;
		$xpath = new \DOMXPath($doc);
		$changed = false;
		$cloak_pattern = preg_quote($config['ipcrypt_prefix'], '~') . ':[A-Z2-7]+(?:\.[a-zA-Z0-9.-]+)?';

		foreach ($xpath->query('//a[starts-with(@href, "?/IP/")]') as $link) {
			if (!preg_match('~^\?/IP/([^/?#]+)(?:#.*)?$~D', $link->getAttribute('href'), $match))
				continue;
			$ip = rawurldecode($match[1]);
			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				if (empty($config['ipcrypt_key']) || !preg_match('~^' . $cloak_pattern . '$~D', $ip))
					continue;
				$ip = uncloak_ip($ip);
			}
			$flag = self::render($ip);
			if ($flag !== '') {
				$fragment = $doc->createDocumentFragment();
				$fragment->appendXML($flag);
				$link->parentNode->insertBefore($fragment, $link->nextSibling);
				$changed = true;
			}
		}

		if (!empty($config['ipcrypt_key'])) {
			$pattern = '~(?<![\w:])' . $cloak_pattern . '(?:/\d+)?(?![\w/])~';
			foreach ($xpath->query('//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::textarea)]') as $node) {
				$text = $node->nodeValue;
				if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE))
					continue;
				$fragment = $doc->createDocumentFragment();
				$offset = 0;
				foreach ($matches[0] as [$cloak, $pos]) {
					$fragment->appendChild($doc->createTextNode(substr($text, $offset, $pos + strlen($cloak) - $offset)));
					$flag = self::render(uncloak_mask($cloak));
					if ($flag !== '') {
						$image = $doc->createDocumentFragment();
						$image->appendXML($flag);
						$fragment->appendChild($image);
						$changed = true;
					}
					$offset = $pos + strlen($cloak);
				}
				$fragment->appendChild($doc->createTextNode(substr($text, $offset)));
				$node->parentNode->replaceChild($fragment, $node);
			}
		}

		if (!$changed)
			return $html;

		$result = '';
		foreach ($root->childNodes as $node)
			$result .= $doc->saveHTML($node);
		return $result;
	}
}
