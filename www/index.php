<?php
define("APP_DIR", "/home/anthony/whitelister/app");
define("CONFIG_FILE", "/home/anthony/whitelister/config.json");

set_include_path(get_include_path() . PATH_SEPARATOR . APP_DIR . "/include");
require_once "PEAR2_Net_RouterOS-1.0.0b5.phar";

// The Net RouterOS library doesn't seemt to autoloading in standard way and breaks things, so
// rather than trying to register our own autoloader, just require each file. Not likely to have
// many

// From: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    $prefix = 'RouterWat\\';

    // base directory for the namespace prefix
    $base_dir = APP_DIR . '/classes/RouterWat/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
},
true, // Throw true
true); // prepend, since the library we include doesn't check prefix

$list = new RouterWat\AddressList('wat_egads_whitelist');

require 'html_wrapper_top.html';

$qvars = [];
parse_str($_SERVER['QUERY_STRING'], $qvars);

$timeUntilUpdateAllowed = $list->timeUntilUpdateAllowed();
$jsData = [
    'time_until_update_allowed' => $timeUntilUpdateAllowed,
];

if (empty($qvars)) {
    // Nothing to do
} elseif ($qvars['action'] == 'add') {
    $address = $qvars['address'];
    $comment = $qvars['comment'];

    $parts = explode("/", $address);
    if (count($parts) > 1) {
        // Assume next bit is subnet
        $validIp = filter_var($parts[0], FILTER_VALIDATE_IP) && is_numeric($parts[1]) && intval($parts[1]) == $parts[1];
        if (!$validIp) {
            echo '<div class="alert alert-dismissible alert-danger" role="alert"><strong>Failed!</strong> '.$address.' is not a valid IP and subnet mask. (Use CIDR notation)<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
    } else {
        $validIp = filter_var($address, FILTER_VALIDATE_IP);
        if (!$validIp) {
            echo '<div class="alert alert-dismissible alert-danger" role="alert"><strong>Failed!</strong> '.$address.' is not a valid IP<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
    }
    if ($validIp) {
        if ($list->hasAddress($address)) {
            echo '<div class="alert alert-dismissible alert-danger" role="alert"><strong>Failed!</strong> '.$address.' already in whitelist<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        } else {
            $list->add($address, $comment);
            echo '<div class="alert alert-dismissible alert-success" role="alert"><strong>Success!</strong> Added '.$address.' to whitelist<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
    }
} elseif ($qvars['action'] == 'remove') {
    $address = $qvars['address'];

    if ($list->hasAddress($address)) {
        try {
            $list->remove($address);
            echo '<div class="alert alert-dismissible alert-success" role="alert"><strong>Success!</strong> Removed '.$address.' from whitelist<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-dismissible alert-danger" role="alert"><strong>Failed!</strong> Cannot remove '.$address.', error occurred.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
    } else {
        echo '<div class="alert alert-dismissible alert-danger" role="alert"><strong>Failed!</strong> Cannot remove '.$address.', not in whitelist<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
    }
} elseif ($qvars['action'] == 'force_update') {
    if ($list->forceUpdate() == 0) {
        echo '<div class="alert alert-dismissible alert-success" role="alert"><strong>Success!</strong> Forced update to router<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
    } else {
        echo '<div id="horses-alert" class="alert alert-dismissible alert-warning" role="alert"><strong>Hold your horses!</strong> Not enough time has passed. You need to wait another <span class="wait-time-left">'.$timeUntilUpdateAllowed.'</span> seconds.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
    }
}
?>

<div class="panel panel-default">
    <div class="panel-heading"><h1 class="panel-title">Add an IP address to list</h1></div>
    <div class="panel-body">
        <form class="form-inline" id="form-add-address" method="get" action="<?= $_SERVER['PHP_SELF'] ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="input-address">IP address: &nbsp;&nbsp;</label>
                <input type="text" class="form-control" name="address" id="input-address" placeholder="IP address goes here">
            </div>
            <div class="form-group">
                <label for="input-comment">&nbsp;&nbsp;Campaign office/note:&nbsp;&nbsp; </label>
                <input type="text" class="form-control" name="comment" id="input-comment" placeholder="Unique name">
            </div>
            &nbsp;&nbsp;
            <button type="submit" class="btn btn-primary">Add new IP</button>
        </form>

    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><h1 class="panel-title">IP addresses in whitelist</h1></div>

    <table class="table" id="addresses">
        <tr>
            <th>IP</th>
            <th>Campaign office / Note</th>
            <th></th>
        </tr>
<?php
foreach ($list->getAddresses() as $address) {
    echo "<tr>\n";
    echo "<td class=\"ip-address\">$address[address]</td>\n";
    echo "<td class=\"comment\">$address[comment]</td>\n";
    echo '<td><a class="pull-right" href="' . $_SERVER['PHP_SELF'] . '?action=remove&address=' . urlencode($address['address']) . '"><span class="fa fa-fw fa-trash-o"></span></td>'."\n";
    echo "</tr>\n";
}
?>
    </table>
</div>


<div class="panel panel-default">
    <div class="panel-heading"><h1 class="panel-title">Force update to router</h1></div>
    <div class="panel-body">
        <p>Forcing an update isn't generally necessary. Router will be updated roughly every minute and a half automatically</p>
        <div id="wait-time-warning" class="<?= $timeUntilUpdateAllowed == 0 ? 'hidden' : ''; ?>">
            Not enough time has ellapsed since the last update.<br/>
            You need to wait another <span class="wait-time-left"><?= $timeUntilUpdateAllowed; ?></span> seconds<br/>
        </div>

        <form class="form-inline <?= $timeUntilUpdateAllowed == 0 ? '' : ' hidden'; ?>" id="form-force-update" method="get" action="<?= $_SERVER['PHP_SELF'] ?>">
            <input type="hidden" name="action" value="force_update">
            <button type="submit" class="btn btn-default"><span class="fa fa-fw fa-refresh"></span> Force router update</button>
        </form>
    </div>
</div>

<?php
require 'html_wrapper_bottom.html';

?>
