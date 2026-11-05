<?php
	$theme = Array();
	
	// Theme name
	$theme['name'] = 'Basic';
	// Description (you can use Tinyboard markup here)
	$theme['description'] = 'Static homepage with news and an optional board directory, recent activity and statistics.';
	$theme['version'] = 'v0.10.0';
	
	// Theme configuration
	$theme['config'] = Array();
	
	$theme['config'][] = Array(
		'title' => 'Head title',
		'name' => 'head_title',
		'type' => 'text'
	);
 
	$theme['config'][] = Array(
		'title' => 'Site title',
		'name' => 'title',
		'type' => 'text',
        'comment' => '(optional)'
	);
	
	$theme['config'][] = Array(
		'title' => 'Slogan',
		'name' => 'subtitle',
		'type' => 'text',
		'comment' => '(optional)'
	);
 
	$theme['config'][] = Array(
		'title' => 'Logo',
		'name' => 'logo',
		'type' => 'textarea',
        'default' => '/static/dd2.png',
		'comment' => 'One image URL per line. Each page load picks a random logo.'
	);
	$theme['config'][] = Array(
		'title' => 'meta',
		'name' => 'meta',
		'type' => 'textarea',
		'comment' => 'Meta text under logo (HTML)'
	);
	
	$theme['config'][] = Array(
		'title' => 'File',
		'name' => 'file',
		'type' => 'text',
		'default' => $config['file_index'],
		'comment' => '(eg. "index.html")'
	);
	
	$theme['config'][] = Array(
		'title' => '# of recent entries',
		'name' => 'no_recent',
		'type' => 'text',
		'default' => 0,
		'size' => 3,
		'comment' => '(number of recent news entries to display; "0" is infinite)'
	);

	$theme['config'][] = Array(
		'title' => 'Active homepage',
		'name' => 'active_homepage',
		'type' => 'checkbox',
		'default' => false,
		'comment' => 'Show the board directory, activity and news. Rebuild with tools/rebuild-homepage.php every five minutes.'
	);
	$theme['config'][] = Array(
		'title' => 'Activity boards',
		'name' => 'activity_boards',
		'type' => 'text',
		'default' => '',
		'comment' => 'Space-separated board names. Only these boards appear in activity and statistics.'
	);
	$theme['config'][] = Array(
		'title' => 'Board links',
		'name' => 'board_links',
		'type' => 'textarea',
		'default' => '',
		'comment' => 'One "board Label" per line, in display order. Add (18+) after adult labels. Empty uses index_boards.'
	);
	foreach (Array(
		'description' => Array('Search description', 'A short plain-text description of the site.'),
		'canonical_url' => Array('Canonical URL', 'Full homepage URL, including https://.'),
		'irc' => Array('IRC address', 'TLS host:port. Optional.'),
		'i2p' => Array('I2P address', 'Full I2P homepage URL. Optional.'),
		'irc_i2p' => Array('IRC over I2P', 'TLS host:port. Optional.')
	) as $name => $field) {
		$theme['config'][] = Array(
			'title' => $field[0], 'name' => $name, 'type' => 'text',
			'default' => '', 'comment' => $field[1]
		);
	}
	
	// Unique function name for building everything
	$theme['build_function'] = 'basic_build';
	$theme['install_callback'] = 'build_install';

	if (!function_exists('build_install')) {
		function build_install($settings) {
			if (!is_numeric($settings['no_recent']) || $settings['no_recent'] < 0)
				return Array(false, '<strong>' . utf8tohtml($settings['no_recent']) . '</strong> is not a non-negative integer.');
			if (!empty($settings['active_homepage']) && trim($settings['activity_boards'] ?? '') === '')
				return Array(false, 'Choose boards for homepage activity.');
		}
	}

