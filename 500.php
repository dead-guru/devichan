<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Error</title>
    <link rel="stylesheet" media="screen" href="<?php echo $config['url_stylesheet'] ?>">
    <link rel="stylesheet" media="screen" href="<?php echo $config['uri_stylesheets'] . $config['default_stylesheet'][0] ?>">
    <script type="text/javascript">
        var active_page = "error";
    </script>
    <script type="text/javascript">
        var configRoot = "<?php echo $config['root'] ?>";
        var inMod = <?php echo $mod ? 'true' : 'false' ?>;
        var modRoot = "<?php echo $config['root'] . 'mod.php' ?>";
    </script>
    <script type="text/javascript" src="<?php echo $config['url_javascript'] ?>"></script>
</head>
<body>
<div class="ban" style="margin-top: 50px;">
    <h2>Error</h2>
    <p style="padding: 15px; font-size: 14px;">
        <?php
        if (isset($message) && $message) {
            $clean_message = $message;
            if (strpos($clean_message, "Stack trace:") !== false) {
                $clean_message = substr($clean_message, 0, strpos($clean_message, "Stack trace:"));
            }
            echo htmlspecialchars($clean_message, ENT_QUOTES, 'UTF-8');
        } else {
            echo 'An error has occurred.';
        }
        ?>
    </p>
    <p style="text-align: center; padding-bottom: 15px;">
        <a href="<?php echo $config['root'] ?>">← Return to homepage</a>
    </p>
</div>
</body>
</html>
