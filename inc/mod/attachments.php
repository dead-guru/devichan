<?php

defined('TINYBOARD') or exit;

function mod_prepare_attachments(&$uploads, $post) {
	global $config, $board;

	$files = json_decode($post['files'], true) ?: array();
	foreach ($_FILES as $name => $upload) {
		if ($upload['error'] === UPLOAD_ERR_NO_FILE) continue;
		if (!preg_match('/^replacement_(\d+)$/', $name, $m) || !isset($files[$m[1]]))
			error($config['error']['invalid']);
		$i = (int)$m[1];
		if ($upload['error'] !== UPLOAD_ERR_OK)
			error(sprintf3($config['error']['phpfileserror'], array('index' => $i + 1, 'code' => $upload['error'])));
		if (!is_uploaded_file($upload['tmp_name']))
			error($config['error']['nomove']);
		if (($_POST['original_file'][$i] ?? null) !== $files[$i]['file']) {
			http_response_code(409);
			error(_('This attachment changed. Reload the post before replacing it.'));
		}
		$ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
		$allowed = $uploads['op'] && $config['allowed_ext_op'] ? $config['allowed_ext_op'] :
			array_merge($config['allowed_ext'], $config['allowed_ext_files']);
		if (!in_array($ext, $allowed)) error($config['error']['unknownext']);
		$id = bin2hex(random_bytes(16));
		$uploads['files'][$i] = array(
			'filename' => mb_substr($upload['name'], 0, $config['max_filename_len']),
			'name' => $upload['name'], 'extension' => $ext, 'file_id' => $id,
			'file' => $id . '.' . $ext,
			'file_path' => $board['dir'] . $config['dir']['img'] . $id . '.' . $ext,
			'thumb' => $id . '.' . ($config['thumb_ext'] ?: $ext),
			'thumb_path' => $board['dir'] . $config['dir']['thumb'] . $id . '.' . ($config['thumb_ext'] ?: $ext),
			'tmp_name' => $upload['tmp_name'], 'size' => filesize($upload['tmp_name']),
			'hash' => md5_file($upload['tmp_name']),
			'is_an_image' => !in_array($ext, $config['allowed_ext_files'])
		);
	}
	if (!$uploads['files']) return $files;

	$sizes = array();
	foreach (array_replace($files, $uploads['files']) as $file) {
		if ($file && $file['file'] != 'deleted') $sizes[] = $file['size'];
	}
	if ($config['multiimage_method'] === 'split')
		$size = array_sum($sizes);
	elseif ($config['multiimage_method'] === 'each')
		$size = max($sizes);
	else
		error(_('Unrecognized file size determination method.'));
	if ($size > $config['max_filesize'])
		error(sprintf3($config['error']['filesize'], array(
			'sz' => number_format($size), 'filesz' => number_format($size),
			'maxsz' => number_format($config['max_filesize'])
		)));

	require_once __DIR__ . '/../image.php';
	foreach ($uploads['files'] as $i => &$file) {
		$spoiler = $config['spoiler_images'] && ($files[$i]['thumb'] ?? null) === 'spoiler';
		if (!move_uploaded_file($file['tmp_name'], $file['file_path']))
			error($config['error']['nomove']);
		if ($file['is_an_image']) {
			if ($config['ie_mime_type_detection'] !== false && preg_match(
				$config['ie_mime_type_detection'], file_get_contents($file['file_path'], false, null, 0, 255)
			)) error($config['error']['mime_exploit']);
			$size = @getimagesize($file['file_path']);
			if (!$size || !in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP)))
				error($config['error']['invalidimg']);
			if ($size[0] > $config['max_width'] || $size[1] > $config['max_height'])
				error($config['error']['maxsize']);
			$image = new Image($file['file_path'], $file['extension'], $size);
			$file['width'] = $image->size->width;
			$file['height'] = $image->size->height;
			if ($spoiler) {
				$file['thumb'] = 'spoiler';
				$size = getimagesize($config['spoiler_image']);
				$file['thumbwidth'] = $size[0];
				$file['thumbheight'] = $size[1];
			} else {
				$thumb = $image->resize($config['thumb_ext'] ?: $file['extension'],
					$uploads['op'] ? $config['thumb_op_width'] : $config['thumb_width'],
					$uploads['op'] ? $config['thumb_op_height'] : $config['thumb_height']);
				$thumb->to($file['thumb_path']);
				$file['thumbwidth'] = $thumb->width;
				$file['thumbheight'] = $thumb->height;
				$thumb->_destroy();
			}
			if ($config['redraw_image'] || ($config['strip_exif'] && in_array($file['extension'], array('jpg', 'jpeg'))))
				$image->to($file['file_path']);
			$image->destroy();
			$file['blurhash'] = (new \Bepsvpt\Blurhash\BlurHash())->encode($file['file_path']);
		} else {
			$file['thumb'] = 'file';
			$icon = $config['file_icons'][$file['extension']] ?? $config['file_icons']['default'];
			$size = getimagesize(sprintf($config['file_thumb'], $icon));
			$file['thumbwidth'] = $size[0];
			$file['thumbheight'] = $size[1];
			if (in_array($file['extension'], array('webm', 'mp4'))) {
				require_once __DIR__ . '/../lib/webm/posthandler.php';
				$video = (object)array('has_file' => true, 'op' => $uploads['op'], 'files' => array((object)$file));
				$had_spoiler = isset($_POST['spoiler']);
				if ($spoiler) $_POST['spoiler'] = true;
				$err = postHandler($video);
				if (!$had_spoiler) unset($_POST['spoiler']);
				$file = (array)$video->files[0];
				if (!in_array($file['thumb'], array('file', 'spoiler')))
					$file['thumb_path'] = $board['dir'] . $config['dir']['thumb'] . $file['thumb'];
				if ($err) error($err);
			}
		}
		$file['size'] = filesize($file['file_path']);
		unset($file['tmp_name']);
		$files[$i] = $file;
	}
	return $files;
}

function mod_attachment_editable($path) {
	$size = @getimagesize($path);
	if (!$size) return false;
	if (in_array($size[2], array(IMAGETYPE_JPEG, IMAGETYPE_BMP))) return true;
	if (!in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP))) return false;

	// Imagick 6 does not report APNG or animated WebP frames consistently.
	if ($size[2] === IMAGETYPE_WEBP) {
		$header = file_get_contents($path, false, null, 0, 21);
		return substr($header, 12, 4) !== 'VP8X' || !(ord($header[20]) & 2);
	}
	if ($size[2] === IMAGETYPE_PNG) {
		$fp = fopen($path, 'rb');
		fseek($fp, 8);
		while (strlen($chunk = fread($fp, 8)) === 8) {
			$type = substr($chunk, 4);
			if ($type === 'acTL' || $type === 'IDAT' || $type === 'IEND') {
				fclose($fp);
				return $type !== 'acTL';
			}
			fseek($fp, unpack('N', $chunk)[1] + 4, SEEK_CUR);
		}
		fclose($fp);
		return false;
	}
	try {
		$image = new Imagick();
		$image->pingImage($path);
		$static = $image->getNumberImages() === 1;
		$image->clear();
		return $static;
	} catch (ImagickException $e) {
		return false;
	}
}
