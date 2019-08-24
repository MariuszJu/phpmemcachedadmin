<style>
    table {
        width: 100%;
        font-size: 13px;
    }
    table thead th {
        border-bottom: 1px solid lightgray;
    }
    table tbody td {
        padding: 5px;
    }
    table tbody td a {
        color: darkred;
    }
</style>

<script>
    var popup = function (url, windowName) {
        var width = document.body.clientWidth;
        var height = document.body.clientHeight;

        var newWindow = window.open(url, windowName, 'height=' + (height * 0.8) + ' ,width=' + (width * 0.8));

        if (window.focus) {
            newWindow.focus()
        }

        return false;
    }
</script>

<?php

require_once 'Library/Bootstrap.php';
require_once 'Library/Command/Memcached.php';

$config = include 'Config/Memcache.php';

if ($a = filter_input(INPUT_GET, 'a', FILTER_SANITIZE_STRING)) {
    switch ($a) {
        case 'delete_key':
            $key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
            $server = filter_input(INPUT_GET, 'server', FILTER_SANITIZE_STRING);
            
            if (!empty($key) && !empty($server) && array_key_exists($server, $config['servers'] ?? [])) {
                $serverConfig = reset($config['servers'][$server]);

                $memcached = new Memcached();
                $memcached->addServer($serverConfig['hostname'], $serverConfig['port']);

                $memcached->delete($key);
            }

            break;

        case 'show_key':
            $key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
            $server = filter_input(INPUT_GET, 'server', FILTER_SANITIZE_STRING);

            if (!empty($key) && !empty($server) && array_key_exists($server, $config['servers'] ?? [])) {
                $serverConfig = reset($config['servers'][$server]);

                $memcached = new Memcached();
                $memcached->addServer($serverConfig['hostname'], $serverConfig['port']);

                echo $memcached->get($key);
                exit;
            }
    }
}

include 'View/Header.phtml';

foreach ($config['servers'] ?? [] as $label => $configs) {
    $total = 0.0;
    $totalKeys = 0;

    echo <<<HTML
        <div class="sub-header corner full-size padding">Server <span class="green">{$label}</span></div>
HTML;

    $config = reset($configs);

    $memcached = new Memcached();
    $memcached->addServer($config['hostname'], $config['port']);

    echo <<<HTML
        <div class="container corner full-size padding">
            <table>
                <thead>
                    <th>Key</th>
                    <th>Size approx.</th>
                    <th>Value</th>
                    <th>Actions</th>
                </thead>
                <tbody>
HTML;

    foreach ($memcached->getAllKeys() as $key) {
        $unit = 'B';
        $decimals = 0;
        $divider = 1;
        $length = strlen($value = $memcached->get($key));

        if ($length > 1024 && $length <= 1024 * 1024) {
            $divider = 1024;
            $unit = 'KB';
            $decimals = 2;
        } else if ($length > 1024 * 1024) {
            $divider = 1024 * 1024;
            $unit = 'MB';
            $decimals = 1;
        }

        $keyLength = (float) number_format($length / $divider, $decimals, '.', '');
        
        $currentUrl = sprintf('%s://%s:%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['SERVER_ADDR'], $_SERVER['SERVER_PORT']);
        $keyUrl = sprintf('%s/keys.php?a=show_key&key=%s&server=%s', $currentUrl, $key, $label);

        $valueHtml = $length <= 10
            ? (is_bool($value) ? ($value ? '(bool) true' : '(bool) false') : $value)
            : sprintf('<button type="button" onclick="popup(\'%s\', \'%s\');">show</button>', $keyUrl, $key);

        echo <<<EOT
            <tr data-key='{$key}'>
                <td style='text-align: right;'>{$key}</td>
                <td>{$keyLength} {$unit}</td>
                <td>{$valueHtml}</td>
                <td><a href='keys.php?a=delete_key&key={$key}&server={$label}'>delete</a></td>
            </tr>
EOT;

        $total += $length / 1024 / 1024;
        $totalKeys++;
    }

    echo '</tbody></table></div>';

    echo '<h4>Total approx.: ' . number_format($total, 2, '.', '') . ' MB, keys: ' . $totalKeys . '</h4>';
}