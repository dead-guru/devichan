<?php

final class MathRenderer
{
	private const MAX_SOURCE_LENGTH = 10000;
	private const MATHML_NAMESPACE = 'http://www.w3.org/1998/Math/MathML';
	private const SAFE_ELEMENTS = [
		'math', 'menclose', 'merror', 'mfenced', 'mfrac', 'mi', 'mmultiscripts',
		'mn', 'mo', 'mover', 'mpadded', 'mphantom', 'mprescripts', 'mroot',
		'mrow', 'ms', 'mspace', 'msqrt', 'mstyle', 'msub', 'msubsup', 'msup',
		'mtable', 'mtd', 'mtext', 'mtr', 'munder', 'munderover', 'none',
	];
	private const SAFE_ATTRIBUTES = [
		'accent', 'accentunder', 'align', 'columnalign', 'columnlines',
		'columnspacing', 'columnspan', 'depth', 'display', 'displaystyle', 'fence',
		'form', 'height', 'largeop', 'linethickness', 'lspace', 'mathvariant',
		'maxsize', 'minsize', 'movablelimits', 'rowalign', 'rowlines', 'rowspan',
		'rowspacing', 'rspace', 'scriptlevel', 'stretchy', 'symmetric', 'voffset',
		'width',
	];
	private const LENGTH_ATTRIBUTES = [
		'columnspacing', 'depth', 'height', 'linethickness', 'lspace', 'maxsize',
		'minsize', 'rowspacing', 'rspace', 'voffset', 'width',
	];

	public static function render(string $escapedLatex): string
	{
		$escapedLatex = trim($escapedLatex);
		if ($escapedLatex === '' || strlen($escapedLatex) > self::MAX_SOURCE_LENGTH) {
			return self::renderSource($escapedLatex);
		}

		try {
			$mathml = Latex2MathML\Converter::convert($escapedLatex, 'block');
			return '<span class="math-rendered">' . self::sanitize($mathml) . '</span>';
		} catch (Throwable $exception) {
			return self::renderSource($escapedLatex);
		}
	}

	private static function sanitize(string $mathml): string
	{
		$document = new DOMDocument('1.0', 'UTF-8');
		$previousErrorMode = libxml_use_internal_errors(true);

		try {
			if (!$document->loadXML($mathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
				throw new RuntimeException('Invalid MathML output.');
			}
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previousErrorMode);
		}

		$root = $document->documentElement;
		if (!$root || $root->localName !== 'math' || $root->namespaceURI !== self::MATHML_NAMESPACE) {
			throw new RuntimeException('Invalid MathML root element.');
		}

		foreach ($document->getElementsByTagName('*') as $element) {
			if (!in_array($element->localName, self::SAFE_ELEMENTS, true)) {
				throw new RuntimeException('Unsafe MathML element.');
			}

			$attributes = [];
			foreach ($element->attributes as $attribute) {
				$attributes[] = $attribute;
			}

			foreach ($attributes as $attribute) {
				if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
					continue;
				}

				$name = $attribute->localName;
				if (!in_array($name, self::SAFE_ATTRIBUTES, true) || !self::isSafeValue($name, $attribute->value)) {
					$element->removeAttributeNode($attribute);
				}
			}
		}

		return $document->saveXML($root);
	}

	private static function isSafeValue(string $name, string $value): bool
	{
		if (!in_array($name, self::LENGTH_ATTRIBUTES, true)) {
			return true;
		}

		$length = '[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:em|ex|px|pt|pc|in|cm|mm|%|mu)?';
		return preg_match('/^(?:' . $length . ')(?:\s+' . $length . ')*$/', $value) === 1;
	}

	private static function renderSource(string $escapedLatex): string
	{
		return '<code class="math-source">[math]' . $escapedLatex . '[/math]</code>';
	}
}
