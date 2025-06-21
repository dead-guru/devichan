<?php

declare(strict_types=1);
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

function event(...$args): bool|string {
	global $events;
	
	$args = func_get_args();
	
	$event = $args[0];
	
	$args = array_splice($args, 1);
	
	if (!isset($events[$event]))
		return false;
	
	foreach ($events[$event] as $callback) {
		if (!is_callable($callback))
			error('Event handler for ' . $event . ' is not callable!');
		if ($error = call_user_func_array($callback, $args))
			return $error;
	}
	
	return false;
}

function event_handler(string $event, callable|string $callback): void {
	global $events;
	
	if (!isset($events[$event]))
		$events[$event] = [];
	
	$events[$event][] = $callback;
}

function reset_events(): void {
	global $events;
	
	$events = [];
}
