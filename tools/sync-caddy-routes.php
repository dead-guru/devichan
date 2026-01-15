<?php

$config = [];

$secrets_file = __DIR__ . '/../inc/secrets.php';
if (file_exists($secrets_file)) {
    $secrets_content = file_get_contents($secrets_file);
    
    if (preg_match('/\$config\s*\[\s*[\'"]secret_boards[\'"]\s*\]\s*=\s*(\[[\s\S]*?\]);/m', $secrets_content, $matches)) {
        eval('$config[\'secret_boards\'] = ' . $matches[1] . ';');
    }
}

$secret_boards = $config['secret_boards'] ?? [];
$board_list = array_keys($secret_boards);
$caddy_url = 'http://caddy:2019';

echo "Secret boards: " . (empty($board_list) ? 'none' : implode(', ', $board_list)) . "\n";

function caddy_get(string $path): ?string {
    global $caddy_url;
    return @file_get_contents("{$caddy_url}{$path}");
}

function caddy_request(string $method, string $path, ?string $json_body = null): array {
    global $caddy_url;
    
    $ch = curl_init("{$caddy_url}{$path}");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    if ($json_body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
    }
    
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $code, 'body' => $result];
}

function build_secret_route(string $board): string {
    $route = [
        '@id' => "secret_board_{$board}",
        'match' => [
            ['path' => ["/{$board}", "/{$board}/", "/{$board}/*"]]
        ],
        'handle' => [
            [
                'handler' => 'subroute',
                'routes' => [
                    [
                        'handle' => [
                            [
                                'handler' => 'reverse_proxy',
                                'upstreams' => [['dial' => '127.0.0.1:80']],
                                'rewrite' => [
                                    'method' => 'GET',
                                    'uri' => "/auth/check.php?board={$board}"
                                ],
                                'handle_response' => [
                                    [
                                        'match' => (object)['status_code' => [2]],
                                        'routes' => []
                                    ],
                                    [
                                        'match' => (object)['status_code' => [401, 403]],
                                        'routes' => [
                                            [
                                                'handle' => [
                                                    [
                                                        'handler' => 'static_response',
                                                        'status_code' => 302,
                                                        'headers' => [
                                                            'Location' => ["/auth/login?board={$board}&redirect={http.request.uri}"]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'handle' => [
                            ['handler' => 'file_server', 'root' => '/var/www']
                        ]
                    ]
                ]
            ]
        ],
        'terminal' => true
    ];
    return json_encode($route);
}

$routes_json = caddy_get('/config/apps/http/servers/srv0/routes');
if ($routes_json === false) {
    echo "ERROR: Cannot connect to Caddy Admin API\n";
    exit(1);
}

echo "Current routes: " . strlen($routes_json) . " bytes\n";

$secret_indices = [];
$routes_raw = json_decode($routes_json);
foreach ($routes_raw as $index => $route) {
    if (isset($route->{'@id'}) && str_starts_with($route->{'@id'}, 'secret_board_')) {
        $board_name = substr($route->{'@id'}, strlen('secret_board_'));
        $secret_indices[$board_name] = $index;
    }
}

echo "Existing secret routes: " . (empty($secret_indices) ? 'none' : implode(', ', array_keys($secret_indices))) . "\n";

foreach (array_reverse($secret_indices, true) as $board => $index) {
    if (!in_array($board, $board_list)) {
        echo "Removing old route for /{$board}/ at index {$index}\n";
        $response = caddy_request('DELETE', "/config/apps/http/servers/srv0/routes/{$index}");
        echo "  " . ($response['code'] >= 200 && $response['code'] < 300 ? 'OK' : "FAILED: {$response['body']}") . "\n";
    }
}

foreach ($board_list as $board) {
    $route_json = build_secret_route($board);
    
    if (isset($secret_indices[$board])) {
        echo "Updating route for /{$board}/\n";
        $response = caddy_request('PATCH', "/config/apps/http/servers/srv0/routes/{$secret_indices[$board]}", $route_json);
    } else {
        echo "Adding route for /{$board}/ at position 0\n";
        $routes_json = caddy_get('/config/apps/http/servers/srv0/routes');
        $all_routes = json_decode($routes_json);
        array_unshift($all_routes, json_decode($route_json));
        
        caddy_request('DELETE', '/config/apps/http/servers/srv0/routes');
        $response = caddy_request('PUT', '/config/apps/http/servers/srv0/routes', json_encode($all_routes));
    }
    
    echo "  " . ($response['code'] >= 200 && $response['code'] < 300 ? 'OK' : "FAILED ({$response['code']}): {$response['body']}") . "\n";
}

echo "\nDone.\n";
