<?php
$mode = @$_GET['mode'];

require_once("captcha.php");

function rand_string($length, $charset) {
  $ret = "";
  while ($length--) {
    $ret .= mb_substr($charset, rand(0, mb_strlen($charset, 'utf-8')-1), 1, 'utf-8');
  }
  return $ret;
}

function cleanup ($pdo, $expires_in) {
  $pdo->prepare("DELETE FROM `captchas` WHERE `created_at` < ?")->execute([time() - $expires_in]);
}

switch ($mode) {
// Request: GET entrypoint.php?mode=get&extra=1234567890
// Response: JSON: cookie => "generatedcookie", captchahtml => "captchahtml", expires_in => 120
// With raw=1: returns PNG image directly (for noscript)
case "get":
  if (!isset ($_GET['extra'])) {
    die();
  }

  $extra = $_GET['extra'];
  $raw = isset($_GET['raw']) && $_GET['raw'] == '1';

  require_once("config.php");

  $text = rand_string($length, $extra);

  $captcha = new CzaksCaptcha($text, $width, $height, $extra);

  $cookie = rand_string(20, "abcdefghijklmnopqrstuvwxyz");

  $query = $pdo->prepare("INSERT INTO `captchas` (`cookie`, `extra`, `text`, `created_at`) VALUES (?, ?, ?, ?)");
  $query->execute(                               [$cookie,  $extra,  $text,  time()]);

  if ($raw) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('captcha_cookie', $cookie, [
      'expires' => time() + $expires_in,
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Strict'
    ]);
    header("Content-type: image/png");
    echo base64_decode($captcha->to_image());
  } else {
    header("Content-type: application/json");
    $html = $captcha->to_html();
    $img = $captcha->to_image();
    echo json_encode(["cookie" => $cookie, "captchahtml" => $html, "image" => $img, "expires_in" => $expires_in]);
  }
  
  break;

// Request: GET entrypoint.php?mode=check&cookie=generatedcookie&extra=1234567890&text=captcha
// Response: 0 OR 1
case "check":
  if (!isset ($_GET['mode'])
   || !isset ($_GET['cookie'])
   || !isset ($_GET['extra'])
   || !isset ($_GET['text'])) {
    die();
  }

  require_once("config.php");

  cleanup($pdo, $expires_in);

  $query = $pdo->prepare("SELECT * FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
  $query->execute([$_GET['cookie'], $_GET['extra']]);

  $ary = $query->fetchAll();

  if (!$ary) {
    echo "0";
  }
  else {
    $query = $pdo->prepare("DELETE FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
    $query->execute([$_GET['cookie'], $_GET['extra']]);

    if ($ary[0]['text'] !== $_GET['text']) {
      echo "0";
    }
    else {
      echo "1";
    }
  }

  break;
}
