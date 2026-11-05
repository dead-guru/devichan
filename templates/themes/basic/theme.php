<?php
	require 'info.php';
	
	function basic_build($action, $settings, $board) {
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		
		Basic::build($action, $settings);
	}
	
	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Basic {
		public static function build($action, $settings) {
			global $config;
			
			if ($action == 'all' || $action == 'news')
				file_write($config['dir']['home'] . $settings['file'], Basic::homepage($settings));
		}
		
		// Build news page
		public static function homepage($settings) {
			global $config;
			
			$settings['no_recent'] = (int) $settings['no_recent'];
			$logos = preg_split('/\s*[\r\n]+\s*/', trim($settings['logo'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
			
			$query = query("SELECT * FROM ``news`` ORDER BY `time` DESC" . ($settings['no_recent'] ? ' LIMIT ' . $settings['no_recent'] : '')) or error(db_error());
			$news = $query->fetchAll(PDO::FETCH_ASSOC);
			
			return Element('themes/basic/index.html', Array(
				'settings' => $settings,
				'logos' => $logos,
				'config' => $config,
				'boardlist' => createBoardlist(),
				'news' => $news
			));
		}
	};
	
?>
