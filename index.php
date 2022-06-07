<?php

$format = '';
$node = '';

$return_code = 0;

if (!file_exists(__DIR__ . '/config.php')) {
    error_log('config.php not found!');
    exit(1);
}

require_once __DIR__ . '/config.php';

if (!isset($_SERVER['PATH_INFO'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    exit(1);
}

foreach ($_GET as $k => $v)
    $_GET[$k] = preg_replace('/[^a-z\-0-9\.]/i', '', $v);

if (isset($_GET['node_full']) && !empty($_GET['node_full']))
    $node = $_GET['node_full'];
elseif (isset($_GET['node']) && !empty($_GET['node']))
    $node = $_GET['node'];

if (isset($_GET['format']) && ($_GET['format'] == 'json'))
    $format = 'json';

if ($_SERVER['PATH_INFO'] == '/nodes.json') {
    $format = 'json';

    $output_store = array();

    $output = ((false !== $output_raw = file_get_contents($oxidized_url . '/nodes.json')) ? json_decode($output_raw, true) : array());
    $output_stored = ((file_exists($oxidized_nodes_cache) && (false !== $output_stored_raw = file_get_contents($oxidized_nodes_cache))) ? json_decode($output_stored_raw, true) : array());

    foreach ($output as $key => $node) {
        $node_name = $node['name'];

        if ($node['status'] == 'success')
            $output_store[$node_name]['last'] = $node['last'];
        elseif ($node['status'] == 'never')
            if (array_key_exists($node_name, $output_stored)) {
                $output[$key]['last'] = $output_stored[$node_name]['last'];
                $output[$key]['status'] = $output_stored[$node_name]['last']['status'];
                $output[$key]['time'] = $output_stored[$node_name]['last']['time'];

                $output_store[$node_name]['last'] = $output_stored[$node_name]['last'];
            }
    }

    file_put_contents($oxidized_nodes_cache, json_encode($output_store, JSON_PRETTY_PRINT), LOCK_EX);
} elseif (preg_match('/^\/node\/fetch\/(.+)/', $_SERVER['PATH_INFO'], $device)) {
    if (preg_match('/[^a-z\-0-9\.]/i', $device[1]) ||
        (false === $output = @file_get_contents($oxidized_repository_path . '/' . $device[1])))
        $return_code = 500;
} elseif (preg_match('/^\/node\/version\/diffs/', $_SERVER['PATH_INFO'])) {
    if (!empty($node) &&
        isset($_GET['oid']) &&
        !empty($_GET['oid']))
        $output = shell_exec('git --no-optional-locks -C ' . $oxidized_repository_path . ' diff ' . escapeshellarg($_GET['oid'] . '^') . ' ' . escapeshellarg($_GET['oid']) . ' ' . escapeshellarg($node));
    else
        $return_code = 500;
} elseif (preg_match('/^\/node\/version\/view/', $_SERVER['PATH_INFO'])) {
    if (!empty($node) &&
        isset($_GET['oid']) &&
        !empty($_GET['oid']))
        $output = shell_exec('git --no-optional-locks -C ' . $oxidized_repository_path . ' show ' . escapeshellarg($_GET['oid'] . ':' . $node));
    else
        $return_code = 500;
} elseif (preg_match('/^\/node\/version/', $_SERVER['PATH_INFO']) &&
          $format == 'json') {
    if (!empty($node)) {
        exec('git --no-optional-locks -C ' . $oxidized_repository_path . " log --pretty=format:'{\"date\":\"%ci\",\"oid\":\"%H\",\"author\":{\"name\":\"%an\",\"email\":\"%ae\",\"time\":\"%ai\"},\"message\":\"%s\"}' " . escapeshellarg($node), $output_shell);
        $output = '[' . implode(',', $output_shell) . ']';
    } else
        $return_code = 500;
} elseif (preg_match('/^\/node\/version/', $_SERVER['PATH_INFO'])) {
    if (!empty($node)) {
        exec('git --no-optional-locks -C ' . $oxidized_repository_path . " log --pretty=format:'{\"date\":\"%ci\",\"oid\":\"%H\",\"author\":{\"name\":\"%an\",\"email\":\"%ae\",\"time\":\"%ai\"},\"message\":\"%s\"}' " . escapeshellarg($node), $output_shell);
        $json = json_decode('[' . implode(',', $output_shell) . ']', true);

        $format = 'html';

        $output = file_get_contents(__DIR__ . '/header.html');
        $output .= "<table class=\"table table-striped\">\n";
        $output .= "<thead>\n";
        $output .= "<tr>\n";
        $output .= "<th scope=\"col\">Date</th>\n";
        $output .= "<th scope=\"col\">Commit ID</th>\n";
        $output .= "<th scope=\"col\">Diffs</th>\n";
        $output .= "<th scope=\"col\">Comment</th>\n";
        $output .= "</tr>\n";
        $output .= "</thead>\n";
        $output .= "<tbody>\n";

        foreach ($json as $line) {
            $output .= "<tr><td>" . $line['date'] . "</td>\n";
            $output .= "<td><a href=\"/node/version/view?node=" . $node . "&oid=" . $line['oid'] . "\">" . $line['oid'] . "</a></td>\n";
            $output .= "<td><a href=\"/node/version/diffs?node=" . $node . "&oid=" . $line['oid'] . "\">diff to previous</a></td>\n";
            $output .= "<td>" . $line['message'] . "</td></tr>\n";
        }

        $output .= "</tbody>\n";
        $output .= "</table>\n";
        $output .= file_get_contents(__DIR__ . '/footer.html');
    } else
        $return_code = 500;
} elseif ($_SERVER['PATH_INFO'] == '/nodes') {
    $nodes = ((false !== $nodes_raw = file_get_contents($oxidized_url . '/nodes.json')) ? json_decode($nodes_raw, true) : array());

    $format = 'html';

    usort($nodes, function($a, $b) {
        return $a['name'] > $b['name'];
    });

    $output = file_get_contents(__DIR__ . '/header.html');
    $output .= "<table class=\"table table-bordered table-striped\" id=\"sorted\">\n";
    $output .= "<thead>\n";
    $output .= "<tr>\n";
    $output .= "<th scope=\"col\">Name</th>\n";
    $output .= "<th scope=\"col\">Model</th>\n";
    $output .= "<th scope=\"col\">Group</th>\n";
    $output .= "<th scope=\"col\">Last status</th>\n";
    $output .= "<th scope=\"col\">Last update</th>\n";
    $output .= "<th scope=\"col\">Last changed</th>\n";
    $output .= "<th scope=\"col\">Actions</th>\n";
    $output .= "</tr>\n";
    $output .= "</thead>\n";
    $output .= "<tbody>\n";

    foreach ($nodes as $node) {
        $output .= "<tr>\n";
        $output .= "<td>" . $node['name'] . "</td>";
        $output .= "<td>" . $node['model'] . "</td>";
        $output .= "<td>" . $node['group'] . "</td>";
        $output .= "<td>" . $node['status'] . "</td>";
        $output .= "<td>" . $node['time'] . "</td>";
        $output .= "<td>-</td>";
        $output .= "<td><a href=\"/node/fetch/" . $node['name'] . "\">Show configuration</a> | ";
        $output .= "<a href=\"/node/version?node_full=" . $node['name'] . "\">Show versions</a></td>";
        $output .= "</tr>\n";
    }

    $output .= "</tbody>\n";
    $output .= "</table>\n";
    $output .= file_get_contents(__DIR__ . '/footer.html');
} else
    $return_code = 500;

if ($return_code == 500) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    exit(1);
} elseif ($format == 'json') {
    header('Content-Type: application/json');

    if (is_array($output))
        echo json_encode($output, JSON_PRETTY_PRINT);
    else
        echo $output;
} elseif ($format == 'html') {
    header('Content-Type: text/html');
    echo $output;
} else {
    header('Content-Type: text/plain');
    echo $output;
}

?>
