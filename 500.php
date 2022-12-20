<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Oops.. Something Went Wrong</title>
    <link rel="stylesheet" media="screen" href="<?php echo $config['url_stylesheet'] ?>">
    <script type="text/javascript">
        var active_page = "error";
    </script>
    <script type="text/javascript">
        var configRoot = "<?php echo $config['root'] ?>";
        var inMod = <?php echo $mod ? 'true' : 'false' ?>;
        var modRoot = "<?php echo $config['root'] . 'mode.php' ?>;
    </script>
    <script type="text/javascript" src="<?php echo $config['url_javascript'] ?>"></script>
</head>
<body>
<h1>Oops... Something Went Wrong</h1>
<a style="display: block; text-align: center; padding-top: 20px;" href="<?php echo $config['root'] ?>">Homepage</a>
<code style="display: none"><?php
    $message = substr($message, 0, strpos($message, "Stack trace:"));
    echo $message;
    ?></code>
</body>
</html>
