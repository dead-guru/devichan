<?php

// Installation/upgrade file
define('VERSION', '5.1.4');

// inc/bootstrap.php needs the Composer autoloader, so this test cannot wait for the test page.
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
	http_response_code(500);
	exit('Composer dependencies are missing. Run <code>composer install --no-dev</code> and reload this page.');
}

require 'inc/bootstrap.php';
loadConfig();

// Salt generators
class SaltGen {
	public $salt_length = 128;

	// Best function I could think of for non-SSL PHP 5
	private function generate_install_salt() {
		$ret = "";

		// This is bad! But what else can we do sans OpenSSL?
		for ($i = 0; $i < $this->salt_length; ++$i) {
			$s = pack("c", mt_rand(0,255));
			$ret = $ret . $s;
		}

		return base64_encode($ret);
	}

	// Best function of the lot. Works with any PHP version as long as OpenSSL extension is on
	private function generate_install_salt_openssl() {
		$ret = openssl_random_pseudo_bytes($this->salt_length, $strong);
		if (!$strong) {
			error(_("Misconfigured system: OpenSSL returning weak salts. Cannot continue."));
		}
		return base64_encode($ret);
	}

	private function generate_install_salt_php7() {
		return base64_encode(random_bytes($this->salt_length));
	}

	// TODO: Perhaps add mcrypt as an option? Maybe overkill.
	public function generate() {
		if (extension_loaded('openssl')) {
			return "OSSL." . $this->generate_install_salt_openssl();
		} else if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION >= 7) {
			return "PHP7." . $this->generate_install_salt_php7();
		} else {
			return "INSECURE." . $this->generate_install_salt();
		}
	}
}

$step = isset($_GET['step']) ? round($_GET['step']) : 0;
$page = array(
	'config' => $config,
	'title' => 'Install',
	'body' => '',
	'nojavascript' => true
);

// this breaks the display of licenses if enabled
$config['minify_html'] = false;

if (file_exists($config['has_installed'])) {
	
	// Check the version number
	$version = trim(file_get_contents($config['has_installed']));
	if (empty($version))
		$version = 'v0.9.1';
	
	function __query($sql) {
		sql_open();
		
		if (mysql_version() >= 50503)
			return query($sql);
		else
			return query(str_replace('utf8mb4', 'utf8', $sql));
	}
	
	$boards = listBoards();
	
	switch ($version) {
		case 'v0.9':
		case 'v0.9.1':
			// Upgrade to v0.9.2-dev
			
			foreach ($boards as &$_board) {
				// Add `capcode` field after `trip`
				query(sprintf("ALTER TABLE `posts_%s` ADD  `capcode` VARCHAR( 50 ) NULL AFTER  `trip`", $_board['uri'])) or error(db_error());
				
				// Resize `trip` to 15 characters
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `trip`  `trip` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev':
			// Upgrade to v0.9.2-dev-1
			
			// New table: `theme_settings`
			query("CREATE TABLE IF NOT EXISTS `theme_settings` ( `name` varchar(40) NOT NULL, `value` text, UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;") or error(db_error());
			
			// New table: `news`
			query("CREATE TABLE IF NOT EXISTS `news` ( `id` int(11) NOT NULL AUTO_INCREMENT, `name` text NOT NULL, `time` int(11) NOT NULL, `subject` text NOT NULL, `body` text NOT NULL, UNIQUE KEY `id` (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;") or error(db_error());
		case 'v0.9.2.1-dev':
		case 'v0.9.2-dev-1':
			// Fix broken version number/mistake
			$version = 'v0.9.2-dev-1';
			// Upgrade to v0.9.2-dev-2
			
			foreach ($boards as &$_board) {
				// Increase field sizes
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `subject` `subject` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `name` `name` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev-2':
			// Upgrade to v0.9.2-dev-3 (v0.9.2)
			
			foreach ($boards as &$_board) {
				// Add `custom_fields` field
				query(sprintf("ALTER TABLE `posts_%s` ADD `embed` TEXT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev-3': // v0.9.2-dev-3 == v0.9.2
		case 'v0.9.2':
			// Upgrade to v0.9.3-dev-1
			
			// Upgrade `theme_settings` table
			query("TRUNCATE TABLE `theme_settings`") or error(db_error());
			query("ALTER TABLE  `theme_settings` ADD  `theme` VARCHAR( 40 ) NOT NULL FIRST") or error(db_error());
			query("ALTER TABLE  `theme_settings` CHANGE  `name`  `name` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
			query("ALTER TABLE  `theme_settings` DROP INDEX  `name`") or error(db_error());
		case 'v0.9.3-dev-1':
			query("ALTER TABLE  `mods` ADD  `boards` TEXT NOT NULL") or error(db_error());
			query("UPDATE `mods` SET `boards` = '*'") or error(db_error());
		case 'v0.9.3-dev-2':
			foreach ($boards as &$_board) {
				query(sprintf("ALTER TABLE `posts_%s` CHANGE `filehash`  `filehash` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-3':
			// Board-specifc bans
			query("ALTER TABLE `bans` ADD  `board` SMALLINT NULL AFTER  `reason`") or error(db_error());
		case 'v0.9.3-dev-4':
			// add ban ID
			query("ALTER TABLE `bans` ADD  `id` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY ( `id` ), ADD UNIQUE (`id`)");
		case 'v0.9.3-dev-5':
			foreach ($boards as &$_board) {
				// Increase subject field size
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `subject` `subject` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-6':
			// change to MyISAM
			$tables = array(
				'bans', 'boards', 'ip_notes', 'modlogs', 'mods', 'mutes', 'noticeboard', 'pms', 'reports', 'robot', 'theme_settings', 'news'
			);
			foreach ($boards as &$board) {
				$tables[] = "posts_{$board['uri']}";
			}
			
			foreach ($tables as &$table) {
				query("ALTER TABLE  `{$table}` ENGINE = MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci") or error(db_error());
			}
		case 'v0.9.3-dev-7':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` CHANGE  `filename` `filename` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-8':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE `posts_%s` ADD INDEX (  `thread` )", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-9':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE `posts_%s`ADD INDEX (  `time` )", $board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s`ADD FULLTEXT (`body`)", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-10':
		case 'v0.9.3':
			query("ALTER TABLE  `bans` DROP INDEX `id`") or error(db_error());
			query("ALTER TABLE  `pms` DROP INDEX  `id`") or error(db_error());
			query("ALTER TABLE  `boards` DROP PRIMARY KEY") or error(db_error());
			query("ALTER TABLE  `reports` DROP INDEX  `id`") or error(db_error());
			query("ALTER TABLE  `boards` DROP INDEX `uri`") or error(db_error());
			
			query("ALTER IGNORE TABLE  `robot` ADD PRIMARY KEY (`hash`)") or error(db_error());
			query("ALTER TABLE  `bans` ADD FULLTEXT (`ip`)") or error(db_error());
			query("ALTER TABLE  `ip_notes` ADD INDEX (`ip`)") or error(db_error());
			query("ALTER TABLE  `modlogs` ADD INDEX (`time`)") or error(db_error());
			query("ALTER TABLE  `boards` ADD PRIMARY KEY(`uri`)") or error(db_error());
			query("ALTER TABLE  `mutes` ADD INDEX (`ip`)") or error(db_error());
			query("ALTER TABLE  `news` ADD INDEX (`time`)") or error(db_error());
			query("ALTER TABLE  `theme_settings` ADD INDEX (`theme`)") or error(db_error());
		case 'v0.9.4-dev-1':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` ADD  `sage` INT( 1 ) NOT NULL AFTER  `locked`", $board['uri'])) or error(db_error());
			}
		case 'v0.9.4-dev-2':
			if (!isset($_GET['confirm'])) {
				$page['title'] = 'License Change';
				$page['body'] = '<p style="text-align:center">You are upgrading to a version which uses an amended license. The licenses included with Tinyboard distributions prior to this version (v0.9.4-dev-2) are still valid for those versions, but no longer apply to this and newer versions.</p>' .
					'<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
					<p style="text-align:center">
						<a href="?confirm=1">I have read and understood the agreement. Proceed to upgrading.</a>
					</p>';
				
				file_write($config['has_installed'], 'v0.9.4-dev-2');
				
				break;
			}
		case 'v0.9.4-dev-3':
		case 'v0.9.4-dev-4':
		case 'v0.9.4':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s`
					CHANGE `subject` `subject` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
					CHANGE  `email`  `email` VARCHAR( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
					CHANGE  `name`  `name` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL", $board['uri'])) or error(db_error());
			}
		case 'v0.9.5-dev-1':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` ADD  `body_nomarkup` TEXT NULL AFTER  `body`", $board['uri'])) or error(db_error());
			}
			query("CREATE TABLE IF NOT EXISTS `cites` (  `board` varchar(8) NOT NULL,  `post` int(11) NOT NULL,  `target_board` varchar(8) NOT NULL,  `target` int(11) NOT NULL,  KEY `target` (`target_board`,`target`),  KEY `post` (`board`,`post`)) ENGINE=MyISAM DEFAULT CHARSET=utf8;") or error(db_error());
		case 'v0.9.5-dev-2':
			query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `title`  `title` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `subtitle`  `subtitle` VARCHAR( 120 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
		case 'v0.9.5-dev-3':
			// v0.9.5
		case 'v0.9.5':
			query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `title`  `title` TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `subtitle`  `subtitle` TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
		case 'v0.9.6-dev-1':
			query("CREATE TABLE IF NOT EXISTS `antispam` (
				  `board` varchar(255) NOT NULL,
				  `thread` int(11) DEFAULT NULL,
				  `hash` bigint(20) NOT NULL,
				  `created` int(11) NOT NULL,
				  `expires` int(11) DEFAULT NULL,
				  `passed` smallint(6) NOT NULL,
				  PRIMARY KEY (`hash`),
				  KEY `board` (`board`,`thread`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;") or error(db_error());
		case 'v0.9.6-dev-2':
			query("ALTER TABLE `boards`
				DROP `id`,
				CHANGE  `uri`  `uri` VARCHAR( 120 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL") or error(db_error());
			query("ALTER TABLE  `bans` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			query("ALTER TABLE  `reports` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			query("ALTER TABLE  `modlogs` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			foreach ($boards as $board) {
				$query = prepare("UPDATE `bans` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));
				
				$query = prepare("UPDATE `modlogs` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));
				
				$query = prepare("UPDATE `reports` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));
			}
		case 'v0.9.6-dev-3':
			query("ALTER TABLE  `antispam` CHANGE  `hash`  `hash` CHAR( 40 ) NOT NULL") or error(db_error());
		case 'v0.9.6-dev-4':
			query("ALTER TABLE  `news` DROP INDEX  `id`, ADD PRIMARY KEY ( `id` )") or error(db_error());
		case 'v0.9.6-dev-5':
			query("ALTER TABLE  `bans` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `mods` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `news` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `noticeboard` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `pms` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `reports` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf("ALTER TABLE  `posts_%s` CHANGE `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-6':
			foreach ($boards as &$_board) {
				query(sprintf("CREATE INDEX `thread_id` ON `posts_%s` (`thread`, `id`)", $_board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s` DROP INDEX `thread`", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-7':
		case 'v0.9.6-dev-7 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0-gold</a>':
			query("ALTER TABLE  `bans` ADD  `seen` BOOLEAN NOT NULL") or error(db_error());
		case 'v0.9.6-dev-8':
		case 'v0.9.6-dev-8 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0.1</a>':
		case 'v0.9.6-dev-8 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0.2</a>':
			query("ALTER TABLE  `mods` CHANGE  `password`  `password` CHAR( 64 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT  'SHA256'") or error(db_error());
			query("ALTER TABLE  `mods` ADD  `salt` CHAR( 32 ) NOT NULL AFTER  `password`") or error(db_error());
			$query = query("SELECT `id`,`password` FROM `mods`") or error(db_error());
			while ($user = $query->fetch(PDO::FETCH_ASSOC)) {
				if (strlen($user['password']) == 40) {
					mt_srand(microtime(true) * 100000 + memory_get_usage(true));
					$salt = md5(uniqid(mt_rand(), true));
			
					$user['salt'] = $salt;
					$user['password'] = hash('sha256', $user['salt'] . $user['password']);
			
					$_query = prepare("UPDATE `mods` SET `password` = :password, `salt` = :salt WHERE `id` = :id");
					$_query->bindValue(':id', $user['id']);
					$_query->bindValue(':password', $user['password']);
					$_query->bindValue(':salt', $user['salt']);
					$_query->execute() or error(db_error($_query));
				}
			}
				case 'v0.9.6-dev-9':
		case 'v0.9.6-dev-9 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0.3</a>':
		case 'v0.9.6-dev-9 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0.4-gold</a>':
		case 'v0.9.6-dev-9 + <a href="https://github.com/vichan-devel/Tinyboard/">vichan-devel-4.0.5-gold</a>':
			foreach ($boards as &$board) {
				__query(sprintf("ALTER TABLE `posts_%s`
					CHANGE `subject` `subject` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `email` `email` VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `name` `name` VARCHAR(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `trip` `trip` VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `capcode` `capcode` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `body` `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
					CHANGE `body_nomarkup` `body_nomarkup` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `thumb` `thumb` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `thumbwidth` `thumbwidth` INT(11) NULL DEFAULT NULL,
					CHANGE `thumbheight` `thumbheight` INT(11) NULL DEFAULT NULL,
					CHANGE `file` `file` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `filename` `filename` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `filehash` `filehash` TEXT CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
					CHANGE `password` `password` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE `ip` `ip` VARCHAR(39) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
					CHANGE `embed` `embed` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;", $board['uri'])) or error(db_error());
			}
			
			__query("ALTER TABLE  `antispam`
				CHANGE  `board`  `board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `hash`  `hash` CHAR( 40 ) CHARACTER SET ASCII COLLATE ascii_bin NOT NULL ,
				DEFAULT CHARACTER SET ASCII COLLATE ascii_bin;") or error(db_error());
			__query("ALTER TABLE  `bans`
				CHANGE  `ip`  `ip` VARCHAR( 39 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `reason`  `reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
				CHANGE  `board`  `board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `title`  `title` TINYTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `subtitle`  `subtitle` TINYTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `cites`
				CHANGE  `board`  `board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `target_board`  `target_board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				DEFAULT CHARACTER SET ASCII COLLATE ascii_general_ci;") or error(db_error());
			__query("ALTER TABLE  `ip_notes`
				CHANGE  `ip`  `ip` VARCHAR( 39 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `body`  `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `modlogs`
				CHANGE  `ip`  `ip` VARCHAR( 39 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `board`  `board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NULL DEFAULT NULL ,
				CHANGE  `text`  `text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `mods`
				CHANGE  `username`  `username` VARCHAR( 30 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `password`  `password` CHAR( 64 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL COMMENT 'SHA256',
				CHANGE  `salt`  `salt` CHAR( 32 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `boards`  `boards` TEXT CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `mutes`
				CHANGE  `ip`  `ip` VARCHAR( 39 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				DEFAULT CHARACTER SET ASCII COLLATE ascii_general_ci;") or error(db_error());
			__query("ALTER TABLE  `news`
				CHANGE  `name`  `name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `subject`  `subject` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `body`  `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `noticeboard`
				CHANGE  `subject`  `subject` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `body`  `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `pms`
				CHANGE  `message`  `message` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `reports`
				CHANGE  `ip`  `ip` VARCHAR( 39 ) CHARACTER SET ASCII COLLATE ascii_general_ci NOT NULL ,
				CHANGE  `board`  `board` VARCHAR( 120 ) CHARACTER SET ASCII COLLATE ascii_general_ci NULL DEFAULT NULL ,
				CHANGE  `reason`  `reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or error(db_error());
			__query("ALTER TABLE  `robot`
				CHANGE  `hash`  `hash` VARCHAR( 40 ) CHARACTER SET ASCII COLLATE ascii_bin NOT NULL COMMENT  'SHA1',
				DEFAULT CHARACTER SET ASCII COLLATE ascii_bin;") or error(db_error());
			__query("ALTER TABLE  `theme_settings`
				CHANGE  `theme`  `theme` VARCHAR( 40 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
				CHANGE  `name`  `name` VARCHAR( 40 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
				CHANGE  `value`  `value` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;") or eror(db_error());
		case 'v0.9.6-dev-10':
			query("ALTER TABLE  `antispam`
				CHANGE  `board`  `board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;") or error(db_error());
			query("ALTER TABLE  `bans`
				CHANGE  `board`  `board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;") or error(db_error());
			query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;") or error(db_error());
			query("ALTER TABLE  `cites`
				CHANGE  `board`  `board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
				CHANGE  `target_board`  `target_board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;") or error(db_error());
			query("ALTER TABLE  `modlogs`
				CHANGE  `board`  `board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;") or error(db_error());
			query("ALTER TABLE  `mods`
				CHANGE  `boards`  `boards` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;") or error(db_error());
			query("ALTER TABLE  `reports`
				CHANGE  `board`  `board` VARCHAR( 58 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;") or error(db_error());
		case 'v0.9.6-dev-11':
		case 'v0.9.6-dev-11 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.6</a>':
		case 'v0.9.6-dev-11 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.7-gold</a>':
		case 'v0.9.6-dev-11 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.8-gold</a>':
		case 'v0.9.6-dev-11 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.9-gold</a>':
			foreach ($boards as &$board) {
				__query(sprintf("ALTER TABLE  ``posts_%s``
					CHANGE  `thumb`  `thumb` VARCHAR( 255 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
					CHANGE  `file`  `file` VARCHAR( 255 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ;",
					$board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-12':
		case 'v0.9.6-dev-12 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.10</a>':
		case 'v0.9.6-dev-12 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.11-gold</a>':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  ``posts_%s`` ADD INDEX `ip` (`ip`)", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-13':
			query("ALTER TABLE ``antispam`` ADD INDEX `expires` (`expires`)") or error(db_error());
		case 'v0.9.6-dev-14':
		case 'v0.9.6-dev-14 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.12</a>':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  ``posts_%s``
					DROP INDEX `body`,
					ADD INDEX `filehash` (`filehash`(40))", $board['uri'])) or error(db_error());
			}
			query("ALTER TABLE ``modlogs`` ADD INDEX `mod` (`mod`)") or error(db_error());
			query("ALTER TABLE ``bans`` DROP INDEX `ip`") or error(db_error());
			query("ALTER TABLE ``bans`` ADD INDEX `ip` (`ip`)") or error(db_error());
			query("ALTER TABLE ``noticeboard`` ADD INDEX `time` (`time`)") or error(db_error());
			query("ALTER TABLE ``pms`` ADD INDEX `to` (`to`, `unread`)") or error(db_error());
		case 'v0.9.6-dev-15':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  ``posts_%s``
					ADD INDEX `list_threads` (`thread`, `sticky`, `bump`)", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-16':
		case 'v0.9.6-dev-16 + <a href="https://int.vichan.net/devel/">vichan-devel-4.0.13</a>':
			query("ALTER TABLE ``bans`` ADD INDEX `seen` (`seen`)") or error(db_error());
		case 'v0.9.6-dev-17':
			query("ALTER TABLE ``ip_notes``
				DROP INDEX `ip`,
				ADD INDEX `ip_lookup` (`ip`, `time`)") or error(db_error());
		case 'v0.9.6-dev-18':
			query("CREATE TABLE IF NOT EXISTS ``flood`` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `ip` varchar(39) NOT NULL,
				  `board` varchar(58) CHARACTER SET utf8 NOT NULL,
				  `time` int(11) NOT NULL,
				  `posthash` char(32) NOT NULL,
				  `filehash` char(32) DEFAULT NULL,
				  `isreply` tinyint(1) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `ip` (`ip`),
				  KEY `posthash` (`posthash`),
				  KEY `filehash` (`filehash`),
				  KEY `time` (`time`)
				) ENGINE=MyISAM DEFAULT CHARSET=ascii COLLATE=ascii_bin AUTO_INCREMENT=1 ;") or error(db_error());
		case 'v0.9.6-dev-19':
			query("UPDATE ``mods`` SET `type` = 10 WHERE `type` = 0") or error(db_error());
			query("UPDATE ``mods`` SET `type` = 20 WHERE `type` = 1") or error(db_error());
			query("UPDATE ``mods`` SET `type` = 30 WHERE `type` = 2") or error(db_error());
			query("ALTER TABLE ``mods`` CHANGE `type`  `type` smallint(1) NOT NULL") or error(db_error());
		case 'v0.9.6-dev-20':
			__query("CREATE TABLE IF NOT EXISTS `bans_new_temp` (
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`ipstart` varbinary(16) NOT NULL,
				`ipend` varbinary(16) DEFAULT NULL,
				`created` int(10) unsigned NOT NULL,
				`expires` int(10) unsigned DEFAULT NULL,
				`board` varchar(58) DEFAULT NULL,
				`creator` int(10) NOT NULL,
				`reason` text,
				`seen` tinyint(1) NOT NULL,
				`post` blob,
				PRIMARY KEY (`id`),
				KEY `expires` (`expires`),
				KEY `ipstart` (`ipstart`,`ipend`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
			$listquery = query("SELECT * FROM ``bans`` ORDER BY `id`") or error(db_error());
			while ($ban = $listquery->fetch(PDO::FETCH_ASSOC)) {
				$query = prepare("INSERT INTO ``bans_new_temp`` VALUES
					(NULL, :ipstart, :ipend, :created, :expires, :board, :creator, :reason, :seen, NULL)");
				
				$range = Bans::parse_range($ban['ip']);
				if ($range === false) {
					// Invalid retard ban; just skip it.
					continue;
				}
				
				$query->bindValue(':ipstart', $range[0]);
				if ($range[1] !== false && $range[1] != $range[0])
					$query->bindValue(':ipend', $range[1]);
				else
					$query->bindValue(':ipend', null, PDO::PARAM_NULL);
				
				$query->bindValue(':created', $ban['set']);
				
				if ($ban['expires'])
					$query->bindValue(':expires', $ban['expires']);
				else
					$query->bindValue(':expires', null, PDO::PARAM_NULL);
				
				if ($ban['board'])
					$query->bindValue(':board', $ban['board']);
				else
					$query->bindValue(':board', null, PDO::PARAM_NULL);
				
				$query->bindValue(':creator', $ban['mod']);
				
				if ($ban['reason'])
					$query->bindValue(':reason', $ban['reason']);
				else
					$query->bindValue(':reason', null, PDO::PARAM_NULL);
				
				$query->bindValue(':seen', $ban['seen']);
				$query->execute() or error(db_error($query));
			}
			
			// Drop old bans table
			query("DROP TABLE ``bans``") or error(db_error());
			// Replace with new table
			query("RENAME TABLE ``bans_new_temp`` TO ``bans``") or error(db_error());
		case 'v0.9.6-dev-21':
		case 'v0.9.6-dev-21 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.90</a>':
			__query("CREATE TABLE IF NOT EXISTS ``ban_appeals`` (
				  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `ban_id` int(10) unsigned NOT NULL,
				  `time` int(10) unsigned NOT NULL,
				  `message` text NOT NULL,
				  `denied` tinyint(1) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `ban_id` (`ban_id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;") or error(db_error());
		case 'v0.9.6-dev-22':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.91</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.92</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.93</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.94</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.95</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.96</a>':
		case 'v0.9.6-dev-22 + <a href="https://int.vichan.net/devel/">vichan-devel-4.4.97</a>':
		case '4.4.97':
			if (!isset($_GET['confirm2'])) {
				$page['title'] = 'License Change';
				$page['body'] = '<p style="text-align:center">You are upgrading to a version which uses an amended license. The licenses included with vichan distributions prior to this version (4.4.98) are still valid for those versions, but no longer apply to this and newer versions.</p>' .
					'<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
					<p style="text-align:center">
						<a href="?confirm2=1">I have read and understood the agreement. Proceed to upgrading.</a>
					</p>';
				
				file_write($config['has_installed'], '4.4.97');
				
				break;
			}
		case '4.4.98-pre':
			if (!$twig) load_twig();
            $cache = $twig->getCache();
            if(is_string($cache) && is_dir($cache)) {
                rrmdir($cache);
            }
		case '4.4.98':
		case '4.5.0':
		case '4.5.1':
		case '4.5.2':
			if (!isset($_GET['confirm3'])) {
				$page['title'] = 'Breaking change';
				$page['body'] = '<p style="text-align:center">You are upgrading to the 5.0 branch of vichan. Please back up your database, because the process is irreversible. At the current time, if you want a very stable vichan experience, please use the 4.5 branch. This warning will be lifted as soon as we all agree that 5.0 branch is stable enough</p>
					<p style="text-align:center">
						<a href="?confirm3=1">I have read and understood the warning. Proceed to upgrading.</a>
					</p>';
				
				file_write($config['has_installed'], '4.5.2');
				
				break;
			}

			foreach ($boards as &$board) {
				query(sprintf('ALTER TABLE ``posts_%s`` ADD `files` text DEFAULT NULL AFTER `bump`;', $board['uri'])) or error(db_error());
				query(sprintf('ALTER TABLE ``posts_%s`` ADD `num_files` int(11) DEFAULT 0 AFTER `files`;', $board['uri'])) or error(db_error());
				query(sprintf('UPDATE ``posts_%s`` SET `files` = CONCAT(\'[{"file":"\',`file`,\'", "thumb":"\',`thumb`,\'", "filename":"\',`filename`,\'", "size":"\',`filesize`,\'",  "width":"\',`filewidth`,\'","height":"\',`fileheight`,\'","thumbwidth":"\',`thumbwidth`,\'","thumbheight":"\',`thumbheight`,\'"}]\') WHERE `file` IS NOT NULL', $board['uri'], $board['uri'], $board['uri'])) or error(db_error());
				query(sprintf('UPDATE ``posts_%s`` SET `num_files` = 1 WHERE `file` IS NOT NULL', $board['uri'])) or error(db_error());
				query(sprintf('ALTER TABLE ``posts_%s`` DROP COLUMN `thumb`, DROP COLUMN `thumbwidth`, DROP COLUMN `thumbheight`, DROP COLUMN `file`, DROP COLUMN `fileheight`, DROP COLUMN `filesize`, DROP COLUMN `filewidth`, DROP COLUMN `filename`', $board['uri'])) or error(db_error());
				query(sprintf('REPAIR TABLE ``posts_%s``', $board['uri']));
			}
		case '4.9.90':
		case '4.9.91':
		case '4.9.92':
                        foreach ($boards as &$board) {
                                query(sprintf('ALTER TABLE ``posts_%s`` ADD `slug` VARCHAR(255) DEFAULT NULL AFTER `embed`;', $board['uri'])) or error(db_error());
			}
                case '4.9.93':
                        query('ALTER TABLE ``mods`` CHANGE `password` `password` VARCHAR(255) NOT NULL;') or error(db_error());
                        query('ALTER TABLE ``mods`` CHANGE `salt` `salt` VARCHAR(64) NOT NULL;') or error(db_error());
		case '5.0.0':
			query('ALTER TABLE ``mods`` CHANGE `salt` `version` VARCHAR(64) NOT NULL;') or error(db_error());
		case '5.0.1':
		case '5.1.0':
			query('CREATE TABLE IF NOT EXISTS ``pages`` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `board` varchar(255) DEFAULT NULL,
			  `name` varchar(255) NOT NULL,
			  `title` varchar(255) DEFAULT NULL,
			  `type` varchar(255) DEFAULT NULL,
			  `content` text,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `u_pages` (`name`,`board`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;') or error(db_error());
		case '5.1.1':
                        foreach ($boards as &$board) {
                                query(sprintf("ALTER TABLE ``posts_%s`` ADD `cycle` int(1) NOT NULL AFTER `locked`", $board['uri'])) or error(db_error());
                        }
		case '5.1.3':
			query('CREATE TABLE IF NOT EXISTS ``captchas`` (
			  	`cookie` varchar(50),
			  	`extra` varchar(200),
			  	`text` varchar(255),
			  	`created_at` int(11),
			  	PRIMARY KEY (`cookie`,`extra`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;') or error(db_error());
		case false:
			// TODO: enhance Tinyboard -> vichan upgrade path.
			query("CREATE TABLE IF NOT EXISTS ``search_queries`` (  `ip` varchar(39) NOT NULL,  `time` int(11) NOT NULL,  `query` text NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;") or error(db_error());

			// Update version number
			file_write($config['has_installed'], VERSION);
			
			$page['title'] = 'Upgraded';
			$page['body'] = '<p style="text-align:center">Successfully upgraded from ' . $version . ' to <strong>' . VERSION . '</strong>.</p>';
			break;
		default:
			$page['title'] = 'Unknown version';
			$page['body'] = '<p style="text-align:center">vichan was unable to determine what version is currently installed.</p>';
			break;
		case VERSION:
			$page['title'] = 'Already installed';
			$page['body'] = '<p style="text-align:center">It appears that vichan is already installed (' . $version . ') and there is nothing to upgrade! Delete <strong>' . $config['has_installed'] . '</strong> to reinstall.</p>';
			break;
	}
	
	die(Element('page.html', $page));
}

function create_config_from_array(&$instance_config, &$array, $prefix = '') {
	foreach ($array as $name => $value) {
		if (is_array($value)) {
			$instance_config .= "\n";
			create_config_from_array($instance_config, $value, $prefix . '[\'' . addslashes($name) . '\']');
			$instance_config .= "\n";
		} else {
			// Checked list items, such as upload formats, extend the defaults from inc/config.php.
			$key = is_int($name) ? '[]' : '[\'' . addslashes($name) . '\']';
			$instance_config .= '	$config' . $prefix . $key . ' = ';

			if (is_bool($value))
				$instance_config .= $value ? 'true' : 'false';
			elseif (is_numeric($value))
				$instance_config .= $value;
			else
				$instance_config .= "'" . addslashes($value) . "'";

			$instance_config .= ";\n";
		}
	}
}

// Finds the thumbnailers and upload formats this server supports. The best thumbnailer comes first.
function media_support() {
	global $config;

	$can_exec = function_exists('shell_exec') && trim((string) shell_exec('echo TEST')) === 'TEST';

	// Use the same PATH as shell_exec_error(), which runs these tools after installation.
	$shell = function ($command) use ($can_exec, $config) {
		if (!$can_exec)
			return '';
		return trim((string) shell_exec('PATH="' . escapeshellcmd($config['shell_path']) . ':$PATH"; ' . $command . ' 2>/dev/null'));
	};
	$has_command = function ($command) use ($shell) {
		return $shell('command -v ' . escapeshellarg($command)) !== '';
	};

	$media = array(
		'imagemagick' => $has_command('convert') && $has_command('identify'),
		'graphicsmagick' => $has_command('gm'),
		'gifsicle' => $has_command('gifsicle'),
		'ffmpeg' => $has_command('ffmpeg') && $has_command('ffprobe'),
		'gd_webp' => function_exists('imagetypes') && (imagetypes() & IMG_WEBP),
	);

	// Ask each tool for the width of a 1x1 lossless WebP image.
	$webp_sample = tempnam($config['tmp'], 'webp');
	file_put_contents($webp_sample, base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='));
	$tools = array(
		'gm' => $media['graphicsmagick'] ? $shell('gm identify -format %w ' . escapeshellarg($webp_sample)) === '1' : null,
		'convert' => $media['imagemagick'] ? $shell('identify -format %w ' . escapeshellarg($webp_sample)) === '1' : null,
		'imagick' => extension_loaded('imagick') ? in_array('WEBP', Imagick::queryFormats('WEBP')) : null,
		'gd' => $media['gd_webp'],
	);
	unlink($webp_sample);

	$methods = array(
		'gm+gifsicle' => 'GraphicsMagick + gifsicle',
		'gm' => 'GraphicsMagick',
		'convert+gifsicle' => 'ImageMagick + gifsicle',
		'convert' => 'ImageMagick',
		'imagick' => 'PHP Imagick',
		'gd' => 'PHP GD',
	);
	$media['thumb_methods'] = array();
	foreach ($methods as $method => $name) {
		$parts = explode('+', $method);
		if ($tools[$parts[0]] === null || (isset($parts[1]) && !$media['gifsicle']))
			continue;
		$media['thumb_methods'][$method] = array('name' => $name, 'webp' => $tools[$parts[0]]);
	}
	$media['thumb_method'] = array_key_first($media['thumb_methods']);
	// Blurhash reads every image through GD, whichever tool makes the thumbnail.
	$media['webp'] = $media['gd_webp'] && $media['thumb_methods'][$media['thumb_method']]['webp'];

	return $media;
}

session_start();

if ($step == 0) {
	// Agreeement
	$page['body'] = '
	<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
	<p style="text-align:center">
		<a href="?step=1">I have read and understood the agreement. Proceed to installation.</a>
	</p>';
	
	echo Element('page.html', $page);
} elseif ($step == 1) {
	$page['title'] = 'Pre-installation test';

	$media = media_support();
	$thumb_method = $media['thumb_method'];

	$ini_bytes = function ($name) {
		$value = trim((string) ini_get($name));
		$bytes = (int) $value;
		// Each suffix falls through to the smaller ones.
		switch (strtolower(substr($value, -1))) {
			case 'g':
				$bytes *= 1024;
			case 'm':
				$bytes *= 1024;
			case 'k':
				$bytes *= 1024;
		}
		return $bytes;
	};
	// post_max_size = 0 disables that limit.
	$upload_limit = min($ini_bytes('upload_max_filesize'), $ini_bytes('post_max_size') ?: PHP_INT_MAX);

	// Extensions required by composer.json and the locked dependencies.
	$extensions = array(
		'mbstring' => 'used for Unicode text handling',
		'ctype' => 'used by Composer dependencies',
		'iconv' => 'used to transliterate slugs',
		'curl' => 'used for captcha checks and uploads by URL',
		'fileinfo' => 'used to detect image types for blurhash',
		'gettext' => 'used for translations',
		'intl' => 'used to format translated dates',
		'exif' => 'used to read image metadata for blurhash',
		'bcmath' => 'used for IP range bans',
		'dom' => 'used for math markup',
	);

	$tests = array(
		array(
			'category' => 'PHP',
			'name' => 'PHP &ge; 8.5',
			'result' => PHP_VERSION_ID >= 80500,
			'required' => true,
			'message' => 'devichan requires PHP 8.5 or newer.',
		),
		array(
			'category' => 'PHP',
			'name' => 'File uploads enabled',
			'result' => (bool) ini_get('file_uploads'),
			'required' => true,
			'message' => 'File uploads are disabled in php.ini (<code>file_uploads</code>), so nobody can post files.',
		),
		array(
			'category' => 'PHP',
			'name' => 'Upload limit &ge; ' . format_bytes($config['max_filesize']),
			'result' => $upload_limit >= $config['max_filesize'],
			'required' => false,
			'message' => 'PHP accepts uploads up to ' . format_bytes($upload_limit) . ' (<code>upload_max_filesize</code>, <code>post_max_size</code>), but <code>$config[\'max_filesize\']</code> allows ' . format_bytes($config['max_filesize']) . '. Raise the php.ini limits or larger files will fail to upload.',
		),
	);

	foreach ($extensions as $extension => $purpose) {
		$tests[] = array(
			'category' => 'PHP',
			'name' => $extension . ' extension installed',
			'result' => extension_loaded($extension),
			'required' => true,
			'message' => 'You must install the PHP <code>' . $extension . '</code> extension, ' . $purpose . '.',
		);
	}

	$tests = array_merge($tests, array(
		array(
			'category' => 'Database',
			'name' => 'PDO extension installed',
			'result' => extension_loaded('pdo'),
			'required' => true,
			'message' => 'You must install the PHP <a href="https://www.php.net/manual/en/book.pdo.php">PDO</a> extension.',
		),
		array(
			'category' => 'Database',
			'name' => 'MySQL PDO driver installed',
			'result' => extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers()),
			'required' => true,
			'message' => 'The required <a href="https://www.php.net/manual/en/ref.pdo-mysql.php">PDO MySQL driver</a> is not installed.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD extension installed',
			'result' => extension_loaded('gd'),
			'required' => true,
			'message' => 'You must install the PHP <a href="https://www.php.net/manual/en/book.image.php">GD</a> extension. GD is a requirement even if you have chosen another image processor for thumbnailing.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: JPEG',
			'result' => function_exists('imagecreatefromjpeg'),
			'required' => true,
			'message' => 'GD was built without JPEG support.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: PNG',
			'result' => function_exists('imagecreatefrompng'),
			'required' => true,
			'message' => 'GD was built without PNG support.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: GIF',
			'result' => function_exists('imagecreatefromgif'),
			'required' => true,
			'message' => 'GD was built without GIF support.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'GD: WebP',
			'result' => $media['gd_webp'],
			'required' => false,
			'message' => '(Optional) GD was built without WebP support, so WebP uploads will be unavailable. Blurhash reads every image through GD, whichever thumbnailer you choose.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'Imagick extension installed',
			'result' => extension_loaded('imagick'),
			'required' => true,
			'message' => 'You must install the PHP <a href="https://www.php.net/manual/en/book.imagick.php">Imagick</a> extension. composer.json requires it, and the moderator attachment editor uses it to detect animated GIFs.',
		),
		array(
			'category' => 'Image processing',
			'name' => '`convert` and `identify` (command-line ImageMagick)',
			'result' => $media['imagemagick'],
			'required' => false,
			'message' => '(Optional) `convert` or `identify` was not found or executable; command-line ImageMagick thumbnailing cannot be enabled.',
		),
		array(
			'category' => 'Image processing',
			'name' => '`gm` (command-line GraphicsMagick)',
			'result' => $media['graphicsmagick'],
			'required' => false,
			'message' => '(Optional) `gm` was not found or executable; command-line GraphicsMagick (faster than ImageMagick) cannot be enabled.',
		),
		array(
			'category' => 'Image processing',
			'name' => '`gifsicle` (command-line animated GIF thumbnailing)',
			'result' => $media['gifsicle'],
			'required' => false,
			'message' => '(Optional) `gifsicle` was not found or executable; animated GIF thumbnails cannot use `gm+gifsicle` or `convert+gifsicle`.',
		),
		array(
			'category' => 'Image processing',
			'name' => 'WebP thumbnails (' . $thumb_method . ')',
			'result' => $media['thumb_methods'][$thumb_method]['webp'],
			'required' => false,
			'message' => '(Optional) The recommended thumbnailer (<code>' . $thumb_method . '</code>) cannot read WebP images, so WebP uploads will be unavailable. Install its WebP support (libwebp).',
		),
		array(
			'category' => 'Video',
			'name' => '`ffmpeg` and `ffprobe` (WebM and MP4)',
			'result' => $media['ffmpeg'],
			'required' => false,
			'message' => '(Optional) `ffmpeg` or `ffprobe` was not found or executable. WebM uploads will fall back to the built-in parser, and MP4 uploads will be unavailable.',
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd(),
			'result' => is_writable('.'),
			'required' => true,
			'message' => 'vichan does not have permission to create directories (boards) here. You will need to <code>chmod</code> (or operating system equivalent) appropriately.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/templates/cache',
			'result' => is_writable('templates') || (is_dir('templates/cache') && is_writable('templates/cache')),
			'required' => true,
			'message' => 'You must give vichan permission to create (and write to) the <code>templates/cache</code> directory or performance will be drastically reduced.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/tmp/cache',
			'result' => is_dir('tmp/cache') && is_writable('tmp/cache'),
			'required' => true,
			'message' => 'You must give vichan permission to write to the <code>tmp/cache</code> directory.'
		),
		array(
			'category' => 'File permissions',
			'name' => getcwd() . '/inc/secrets.php',
			'result' => is_writable('inc/secrets.php'),
			'required' => false,
			'message' => 'vichan does not have permission to make changes to <code>inc/secrets.php</code>. To complete the installation, you will be asked to manually copy and paste code into the file instead.'
		),
		array(
			'category' => 'Misc',
			'name' => 'Caching available (APCu, Memcached or Redis)',
			'result' => extension_loaded('apcu') || extension_loaded('memcached') || extension_loaded('redis'),
			'required' => false,
			'message' => 'You will not be able to enable the additional caching system, designed to minimize SQL queries and significantly improve performance. Install <a href="https://www.php.net/manual/en/book.apcu.php">APCu</a>, <a href="https://www.php.net/manual/en/book.memcached.php">Memcached</a> or <a href="https://pecl.php.net/package/redis">Redis</a>.'
		),
		array(
			'category' => 'Misc',
			'name' => 'OpenSSL RC4-40 (IP cloaking)',
			'result' => extension_loaded('openssl') && @openssl_encrypt("\0\0\0\0", 'rc4-40', 'devichan', OPENSSL_RAW_DATA) !== false,
			'required' => false,
			'message' => '(Optional) OpenSSL cannot use the RC4-40 cipher, so IP cloaking (<code>$config[\'ipcrypt_key\']</code>) will not work. With OpenSSL 3, enable the legacy provider as <code>php-fpm/Dockerfile</code> does.'
		),
		array(
			'category' => 'Misc',
			'name' => 'devichan installed using git',
			'result' => is_dir('.git'),
			'required' => false,
			'message' => '(Optional) devichan was not installed from the git repository. Using git makes upgrading much easier.'
		)
	));

	$config['font_awesome'] = true;


	echo Element('page.html', array(
		'body' => Element('installer/check-requirements.html', array(
			'tests' => $tests,
			'config' => $config,
		)),
		'title' => 'Checking environment',
		'config' => $config,
	));
} elseif ($step == 2) {

	// Basic config
	$page['title'] = 'Configuration';

	$sg = new SaltGen();
	$config['cookies']['salt'] = $sg->generate();
	$config['secure_trip_salt'] = $sg->generate();

	// The generated inc/secrets.php reads these variables before the values from this form.
	$env = array();
	foreach (array('server' => 'MYSQL_HOST', 'database' => 'MYSQL_DATABASE', 'user' => 'MYSQL_USER', 'password' => 'MYSQL_PASSWORD') as $field => $variable) {
		if (getenv($variable)) {
			$env[$field] = $variable;
			if ($field != 'password')
				$config['db'][$field] = getenv($variable);
		}
	}

	echo Element('page.html', array(
		'body' => Element('installer/config.html', array(
			'config' => $config,
			'env' => $env,
			'media' => media_support(),
		)),
		'title' => 'Configuration',
		'config' => $config
	));
} elseif ($step == 3) {
	$more = $_POST['more'];
	unset($_POST['more']);

	$db_config = $_POST['db'];
	unset($_POST['db']);

	$instance_config =
'<'.'?php

/*
*  Instance Configuration
*  ----------------------
*  Edit this file and not config.php for imageboard configuration.
*
*  You can copy values from config.php (defaults) and paste them here.
*/


	$config[\'db\'][\'server\'] = getenv(\'MYSQL_HOST\') ?: \'' . addslashes($db_config['server']) . '\';
	$config[\'db\'][\'database\'] = getenv(\'MYSQL_DATABASE\') ?: \'' . addslashes($db_config['database']) . '\';
	$config[\'db\'][\'prefix\'] = \'' . addslashes($db_config['prefix']) . '\';
	$config[\'db\'][\'user\'] = getenv(\'MYSQL_USER\') ?: \'' . addslashes($db_config['user']) . '\';
	$config[\'db\'][\'password\'] = getenv(\'MYSQL_PASSWORD\') ?: \'' . addslashes($db_config['password']) . '\';

';
	
	create_config_from_array($instance_config, $_POST);
	
	$instance_config .= "\n";
	$instance_config .= $more;
	$instance_config .= "\n";
	
	if (@file_put_contents('inc/secrets.php', $instance_config)) {
		// flushes opcache if php >= 5.5.0 or opcache is installed via PECL
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate('inc/secrets.php');
		}
		header('Location: ?step=4', true, $config['redirect_http']);
	} else {
		$page['title'] = 'Manual installation required';
		$page['body'] = '
			<p>I couldn\'t write to <strong>inc/secrets.php</strong> with the new configuration, probably due to a permissions error.</p>
			<p>Please complete the installation manually by copying and pasting the following code into the contents of <strong>inc/secrets.php</strong>:</p>
			<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black">' . htmlentities($instance_config) . '</textarea>
			<p style="text-align:center">
				<a href="?step=4">Once complete, click here to complete installation.</a>
			</p>
		';
		echo Element('page.html', $page);
	}
} elseif ($step == 4) {
	// SQL installation
	
	buildJavascript();
	
	$sql = @file_get_contents('install.sql') or error("Couldn't load install.sql.");
	
	sql_open();
	$mysql_version = mysql_version();
	
	// This code is probably horrible, but what I'm trying
	// to do is find all of the SQL queires and put them
	// in an array.
	preg_match_all("/(^|\n)((SET|CREATE|INSERT).+)\n\n/msU", $sql, $queries);
	$queries = $queries[2];
	
	$queries[] = Element('posts.sql', array('board' => 'b'));
	
	$sql_errors = '';
	foreach ($queries as $query) {
		if ($mysql_version < 50503)
			$query = preg_replace('/(CHARSET=|CHARACTER SET )utf8mb4/', '$1utf8', $query);
		$query = preg_replace('/^([\w\s]*)`([0-9a-zA-Z$_\x{0080}-\x{FFFF}]+)`/u', '$1``$2``', $query);
		if (!query($query))
			$sql_errors .= '<li>' . db_error() . '</li>';
	}
	
	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using vichan. Please remember to report any bugs you discover. <a href="https://github.com/vichan-devel/vichan/wiki/Configuration-Basics">How do I edit the config files?</a></p>';
	
	if (!empty($sql_errors)) {
		$page['body'] .= '<div class="ban"><h2>SQL errors</h2><p>SQL errors were encountered when trying to install the database. This may be the result of using a database which is already occupied with a vichan installation; if so, you can probably ignore this.</p><p>The errors encountered were:</p><ul>' . $sql_errors . '</ul><p><a href="?step=5">Ignore errors and complete installation.</a></p></div>';
	} else {
		$boards = listBoards();
		foreach ($boards as &$_board) {
			setupBoard($_board);
			buildIndex();
		}
		
		file_write($config['has_installed'], VERSION);
		/*if (!file_unlink(__FILE__)) {
			$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
		}*/
	}
	
	echo Element('page.html', $page);
} elseif ($step == 5) {
	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using vichan. Please remember to report any bugs you discover.</p>';
	
	$boards = listBoards();
	foreach ($boards as &$_board) {
		setupBoard($_board);
		buildIndex();
	}
	
	file_write($config['has_installed'], VERSION);
	if (!file_unlink(__FILE__)) {
		$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
	}
	
	echo Element('page.html', $page);
}

