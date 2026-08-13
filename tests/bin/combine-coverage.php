<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;

$projectDirectory = dirname(__DIR__, 2);

require $projectDirectory . '/vendor/autoload.php';

$arguments = $argv;
array_shift($arguments);
$output = array_shift($arguments);
$inputs = $arguments;

if ($output === null || $inputs === []) {
    fwrite(STDERR, "Pass an output file and at least one coverage chunk.\n");
    exit(1);
}

$coverage = null;
foreach ($inputs as $input) {
    $chunk = require $input;
    if (!$chunk instanceof CodeCoverage) {
        fwrite(STDERR, "Invalid coverage chunk: {$input}\n");
        exit(1);
    }

    if ($coverage === null) {
        $coverage = $chunk;
        continue;
    }

    $data = $coverage->getData();
    $data->merge($chunk->getData());
    $coverage->setData($data);
    $coverage->setTests(array_merge($coverage->getTests(), $chunk->getTests()));
}

$serialized = base64_encode(serialize($coverage));
file_put_contents($output, "<?php\nreturn unserialize(base64_decode('{$serialized}'));\n");

printf("Combined %d CLI coverage chunks into %s.\n", count($inputs), $output);
