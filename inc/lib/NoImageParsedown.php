<?php

final class NoImageParsedown extends Parsedown {
	protected function inlineImage($excerpt) {
		return null;
	}
}
