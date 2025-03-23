<?php
/**
 * Bulk VICIDIAL User & Phone Creator
 * 
 * A contribution to the VICIDIAL community by StopManualDial.com.
 * 
 * This tool automates the process of creating VICIDIAL users and phones in bulk,
 * saving time and effort for VICIDIAL administrators.
 * 
 * Website: https://stopmanualdial.com
 * GitHub: https://github.com/edvalbuena/VICIDIAL-STUFF 
 * 
 * Author: Edwin Valbuena Jr. 
 * Version: 1.0
 * License: Just send me bitcoin: 14cqtV63Ei9qEdNmDb5xHCjUi88Rpirdz6
 */

// CONFIG
$DBhost = 'localhost';
$DBname = 'asterisk';
$DBuser = 'cron';
$DBpass = '1234'; // Because everyone's using 1234. Change it to the dbpassword

$mysqli = new mysqli($DBhost, $DBuser, $DBpass, $DBname);
if ($mysqli->connect_error) die("DB Connection failed: " . $mysqli->connect_error);

function getServers($mysqli) {
    $opts = "";
    $q = $mysqli->query("SELECT server_ip, server_description FROM servers");
    if (!$q) die("Error fetching servers: " . $mysqli->error);
    while ($r = $q->fetch_assoc()) {
        $opts .= "<option value='{$r['server_ip']}'>{$r['server_ip']} - {$r['server_description']}</option>";
    }
    return $opts;
}

function getPhones($mysqli) {
    $opts = "";
    $q = $mysqli->query("SELECT extension FROM phones ORDER BY extension");
    if (!$q) die("Error fetching phones: " . $mysqli->error);
    while ($r = $q->fetch_assoc()) {
        $opts .= "<option value='{$r['extension']}'>{$r['extension']}</option>";
    }
    return $opts;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base = $_POST['base_username'];
    $start = (int)$_POST['starting_number'];
    $qty = (int)$_POST['quantity'];
    $user_group = $_POST['user_group'];
    $ref_user = $_POST['reference_user'];
    $ref_phone = $_POST['reference_phone'];
    $server_ip = $_POST['server_ip'];
    $password = 'Dialer2025';

    // Fetch templates
    $refU = $mysqli->query("SELECT * FROM vicidial_users WHERE user='$ref_user' LIMIT 1");
    if (!$refU || $refU->num_rows === 0) die("Reference user not found.");

    $refP = $mysqli->query("SELECT * FROM phones WHERE extension='$ref_phone' LIMIT 1");
    if (!$refP || $refP->num_rows === 0) die("Reference phone not found.");

    $user_template = $refU->fetch_assoc();
    $phone_template = $refP->fetch_assoc();

    // Get all columns in correct order
    $user_cols = [];
    $colq = $mysqli->query("SHOW COLUMNS FROM vicidial_users");
    if (!$colq) die("Error fetching user columns: " . $mysqli->error);
    while ($r = $colq->fetch_assoc()) $user_cols[] = $r['Field'];

    $phone_cols = [];
    $colq = $mysqli->query("SHOW COLUMNS FROM phones");
    if (!$colq) die("Error fetching phone columns: " . $mysqli->error);
    while ($r = $colq->fetch_assoc()) $phone_cols[] = $r['Field'];

    // Dialplan number
    $dpq = $mysqli->query("SELECT MAX(CAST(dialplan_number AS UNSIGNED)) as maxdp FROM phones");
    $dp = ($dpq && $dpq->num_rows) ? (int)$dpq->fetch_assoc()['maxdp'] + 1 : 10001;

    // Begin CSV capture
    $csv = [];
    $csv[] = ['user', 'pass', 'full_name', 'extension', 'dialplan_number', 'server_ip', 'user_group'];

    for ($i = 0; $i < $qty; $i++) {
        $suffix = str_pad($start + $i, 3, '0', STR_PAD_LEFT);
        $user = $base . $suffix;

        // Skip existing
        $chkU = $mysqli->query("SELECT user FROM vicidial_users WHERE user='$user'");
        $chkP = $mysqli->query("SELECT extension FROM phones WHERE extension='$user'");
        if ($chkU->num_rows || $chkP->num_rows) continue;

        // Copy user
        $u = $user_template;
        $u['user'] = $user;
        $u['pass'] = $password;
        $u['full_name'] = strtoupper($user);
        $u['phone_login'] = $user;
        $u['phone_pass'] = $password;
        $u['user_group'] = $user_group;
        $u['user_id'] = 90000 + $start + $i;

        $ucols = [];
        $uvals = [];
        foreach ($user_cols as $col) {
            $ucols[] = "`$col`";
            $uvals[] = "'" . $mysqli->real_escape_string($u[$col] ?? '') . "'";
        }

        $usql = "INSERT INTO vicidial_users (" . implode(",", $ucols) . ") VALUES (" . implode(",", $uvals) . ")";
        if (!$mysqli->query($usql)) die("Error creating user: " . $mysqli->error);

        // Copy phone
        $p = $phone_template;
        $p['extension'] = $user;
        $p['login'] = $user;
        $p['pass'] = $password;
        $p['fullname'] = strtoupper($user);
        $p['server_ip'] = $server_ip;
        $p['dialplan_number'] = $dp;
        $p['voicemail_id'] = $dp;
        $p['outbound_cid'] = $dp;
        $p['phone_alias'] = $user;
        $p['user_group'] = $user_group;

        $pcols = [];
        $pvals = [];
        foreach ($phone_cols as $col) {
            $pcols[] = "`$col`";
            $pvals[] = "'" . $mysqli->real_escape_string($p[$col] ?? '') . "'";
        }

        $psql = "INSERT INTO phones (" . implode(",", $pcols) . ") VALUES (" . implode(",", $pvals) . ")";
        if (!$mysqli->query($psql)) die("Error creating phone: " . $mysqli->error);

        // Add to CSV
        $csv[] = [$user, $password, strtoupper($user), $user, $dp, $server_ip, $user_group];
        $dp++;
    }

    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=bulk_users_phones_' . date('Ymd_His') . '.csv');
    $f = fopen('php://output', 'w');
    foreach ($csv as $line) fputcsv($f, $line);
    fclose($f);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bulk VICIDIAL Copy (User + Phone)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h2 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 200px;
        }
        .watermark {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="https://stopmanualdial.com/stopmanualdial.png" alt="StopManualDial Logo">
    </div>
    <h2>Bulk VICIDIAL User & Phone Creator</h2>
    <form method="post">
        <label>Base Username (e.g., Agent or number like master001 or 1001):</label>
        <input type="text" name="base_username" required>

        <label>Starting Number:</label>
        <input type="number" name="starting_number" value="1" min="1" required>

        <label>Quantity to Create:</label>
        <input type="number" name="quantity" value="5" min="1" max="500">

        <label>User Group:</label>
        <input type="text" name="user_group" value="spain" required>

        <label>Reference User (Copy settings from):</label>
        <input type="text" name="reference_user" value="spain001" required>

        <label>Reference Phone (Copy settings from):</label>
        <select name="reference_phone" required>
            <?= getPhones($mysqli); ?>
        </select>

        <label>Phone Server IP:</label>
        <select name="server_ip" required>
            <?= getServers($mysqli); ?>
        </select>

        <button type="submit">Create & Download CSV</button>
    </form>
    <div class="watermark">
        <p>Powered by <a href="https://stopmanualdial.com" target="_blank">StopManualDial.com</a></p>
    </div>
</body>
</html>
