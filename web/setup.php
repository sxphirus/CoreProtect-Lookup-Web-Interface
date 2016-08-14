<?php
// CoLWI v0.9.0
// Setup JSON application or Page
// Copyright (c) 2015-2016 SimonOrJ

// POST parameters:
// (To be populated...)

/*
Prereq:
- Have write access to config.php
- Recommend write access to cache/ directory
*/

// Testing script
error_reporting(-1);ini_set('display_errors', 'On');

// Load configuration
$c = require "config.php";
$cj = json_decode(file_get_contents("config.json"), true);

// Login and permission check
require "res/php/login.php";
$login = new Login($c);
if (!$login->permission(Login::PERM_SETUP)) {
    header("Location: login.php?landing=setup.php");
    exit();
}

include "pdowrapper.php";

// Check if config is editable and set it to a variable;
//   and check if GET of submit is set.
if (($writePerm = is_writable("config.php") && is_writable("config.json") && is_writable("server/")) && !empty($_GET['submit'])) {
    // Out variable
    $out = array(0);

    switch ($_GET['submit']) {
        case "server":
            // Possible: delete, new, update
            
            // Check for empty name, then set and check file name
            if (empty($_POST['name'])) {
                // Empty name means new.
                if (empty($_POST['newname'])) {
                    $out = array(5, "Server name is empty.");
                    break;
                }
                $file = $_POST['newname'];
            } else {
                $file = $_POST['name'];
            }
            
            // Check for valid name
            if (empty($_POST['name']) && ($file = preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file)) !== $_POST['newname']) {
                // warn about illegal server name
                $out = array(5, "Server name includes illegal characters. Consider using \"".$file."\".");
                break;
            }
            
            $file = "server/".$file;
            
            if (isset($_POST['delete']) && $_POST['delete'] === "on") {
                if (file_exists($file.".php")) {
                    // Check for JSON file
                    if (file_exists($file.".json")) {
                        unlink($file.".json");
                    }
                    // get rid of the server file.
                    unlink($file.".php");
                } else {
                    $out = array(1, "Server does not exist.");
                }
                // Break out of the switch case
                break;
            }
            
            // Possible: new, update
            $s = array();
            $sj = array();
            
            if (empty($_POST['prefix'])) {
                // Check for empty required fields
                $out = array(5, '"prefix\" field must be filled out.');
                break;
            } elseif (strpos(";", $_POST['prefix']) !== false) {
                // Prefix check for injection prevention
                $out = array(5, 'Injection detected on the "prefix" field.');
                break;
            }
            
            // There is be a choice to configure database connection info on
            //   the server through SSH due to security concerns.
            
            // However, if you want to set it up right from the browser, here
            //   goes the verification...
            
            // TODO: Make verificaiton on the tool for hand-written server info.
            if (empty($_POST['nodb']) || $_POST['nodb'] !== "on") {
                // Connect to the server.
                $codb = pdoWrapper($_POST);

                if (is_a($codb, "PDOException")) {
                    // Database Connection Unsuccessful
                    $out = array(3, $codb->getMessage(), $codb->getCode());
                    break;
                }
                
                // Check the version.
                $v = array("0","0");
                $legacy = false;
                foreach ($codb->query("SELECT version FROM ".$_POST['prefix']."version;") as $r) {
                    $v = explode(".", $r['version']);
                    if ($v[0] <= 2) {
                        if ($v[0] == 2 && $v[1] < 11) {
                            // This started from an old version of CP.
                            $legacy = true;
                        } elseif ($v[0] == 1) {
                            // Same here, but comes from CP v1.x.
                            $legacy = true;
                        }
                    }
                }
                if ($v[0] != 2 || $v[1] < 11) {
                    $out = array(5, "CoreProtect too old. Update to v2.11 or above.");
                    break;
                }
            } else {
                // Assume database is new for now.
                $legacy = false;
            }
            
            if (empty($_POST['name'])) {
                // New server
                if (!file_exists($file.".php")) {
                    // Database
                    if ($_POST['type'] === "mysql") {
                        $s = array(
                            'db' => array (
                                'type' => 'mysql',
                                'host' => $_POST['host'],
                                'user' => $_POST['user'],
                                'pass' => $_POST['pass'],
                                'data' => $_POST['data']
                            )
                        );
                    }
                    elseif ($_POST['type'] === "sqlite") {
                        $s = array(
                            'db' => array (
                                'type' => 'sqlite',
                                'path' => $_POST['path']
                            )
                        );
                    }

                    $s['co'] = $_POST['prefix'];
                    $s['legacy'] = $legacy;
                    
                    // Dynmap setup
                    $sj['dynmap']['URL'] = $_POST['dynmapURL'];
                    $sj['dynmap']['zoom'] = $_POST['dynmapZoom'] ? $_POST['dynmapZoom'] : 6;
                    $sj['dynmap']['map'] = $_POST['dynmapMap'] ? $_POST['dynmapMap'] : "flat";
                } else {
                    // Server with that name exists already.
                    $out = array(5, "Server with this name already exists.");
                    break;
                }
            } else {
                // Update existing server
                if (file_exists($file)) {
                    
                    // Check if any update variable was set.
                    if (empty($_POST['update'])) {
                        $out = array(5, "There is nothing to update.");
                        break;
                    }
                    
                    // Load the file
                    $s = include $file;
                    
                    // If this is in the "update" array, do the following.
                    if (in_array("db",$_POST['update'],true)) $s['db'] = $_POST['type'] === "mysql"
                            ? array (
                                'type' => 'mysql',
                                'host' => $_POST['host'],
                                'user' => $_POST['user'],
                                'pass' => $_POST['pass'],
                                'data' => $_POST['data']
                            )
                            : array (
                                'type' => 'sqlite',
                                'path' => $_POST['path']
                            );
                    // TODO: make it possible to re-evaluate legacy variable.
                    if (in_array("prefix",$_POST['update'],true))       $s['co'] = $_POST['prefix'];
                    if (in_array("dynmapURL",$_POST['update'],true))   $sj['dynmap']['URL'] = $_POST['dynmapURL'];
                    if (in_array("dynmapZoom",$_POST['update'],true))   $sj['dynmap']['zoom'] = $_POST['dynmapZoom'] ? $_POST['dynmapZoom'] : 6;
                    if (in_array("dynmapMap",$_POST['update'],true))    $sj['dynmap']['map'] = $_POST['dynmapMap'] ? $_POST['dynmapMap'] : "flat";
                } else {
                    // Server with that name does not exist.
                    $out = array(5, "Server with this name does not exist.");
                    break;
                }
            }
            
            // Save file
            file_put_contents($file.".php","<?php return ".var_export($s,true).";?>");
            file_put_contents($file.".json",json_encode($sj));
            
            break;
        case "user":
            // TODO: Make user account creation possible.
            break;
        case "config":
        default:
            // TODO: make this work.
            if (!empty($_POST['navbar']))$c['navbar'] = array (
                'Home' => '/',
            );
            
            $cj = array('form'=>array());
            if (!empty($_POST['dateFormat']))   $cj['form']['dateFormat']   = $_POST['dateFormat'];
            if (!empty($_POST['timeFormat']))   $cj['form']['timeFormat']   = $_POST['timeFormat'];
            if (!empty($_POST['timeDividor']))  $cj['form']['timeDividor']  = intval($_POST['timeDividor']);
            if (!empty($_POST['pageInterval'])) $cj['form']['pageInterval'] = intval($_POST['pageInterval']);
            if (!empty($_POST['bukkitToMc']))   $c['flag']['bukkitToMc']   = $_POST['pageInterval'] === true;
            if (!empty($_POST['copyright']))    $c['copyright']            = $_POST['copyright'];
            
            // Save file
            file_put_contents("config.php", "<?php return ".var_export($c,true).";?>");
            file_put_contents("config.json", json_encode($cj));

            break;
    }
    
    // If js works, return JSON of $out
    if (isset($_POST['js']) && $_POST['js'] !== "disabled") {
        header('Content-type:application/json;charset=utf-8');
        echo json_encode($out, JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit();
    }
} elseif (!empty($_GET['server'])) {
    // Get Server Config
    // TODO: Write this.
        $file = "server/".$_GET['server'];
        if (file_exists($file.".php")) {
            $d = require $file.".php";
            $dj = json_decode(file_get_contents($file.".json"), true);
            
            // To avoid database credentials leak
            unset($d['db']);
        } else {
            // Empty array
            $d = array();
        }
        
        header('Content-type:application/json;charset=utf-8');
        echo json_encode(array_merge($d,$dj), JSON_PARTIAL_OUTPUT_ON_ERROR);

        exit();
}

$c = include "config.php";

// If not called from the interface, then:
if (empty($_GET["fromLookup"])):
require "res/php/webtemplate.php";
$template = new WebTemplate($c, $login->getUsername(), "Setup &bull; CoLWI");
?>
<!doctype html>
<html>

<?php $template->head();?>

<body>

<!-- Top navigation bar -->
<?php $template->navbar();?>
<?php endif; ?>
<!-- Server Settings -->
<div class="container">
<?php if (!$writePerm):?>
<p>Please make sure the webserver has write permissions to the <code>config.php</code>, <code>config.json</code> and, <code>server/</code> files and directory.</p>
<?php else:?>
<div id="setupDbForm" class="card">
<div class="card-header"><span class="h4 card-title">Server Configuration</span></div>
<form id="setupDb" class="card-block" role="form" method="post" action="./setup.php?submit=server">
<input id="jsCheck" type="hidden" name="js" value="disabled">
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbSele">Server Name</label>
  <div class="col-sm-10">
    <div class="input-group">
      <select class="form-control" id="dbSele" name="name">
        <option value="">new</option>
        <option disabled>------</option>
<?php
// List servers
$sv = new FilesystemIterator("server/");
foreach ($sv as $fi) {
    if ($fi->getExtension() !== "php") continue;
    echo "<option>".$fi->getBasename(".php")."</option>";
}
?>
      </select>
      <span class="input-group-btn c2" style="width:0"></span>
      <input class="form-control" type="text" id="dbName" name="newname" placeholder="My Awesome Server">
    </div>
  </div>
</div>
<div class="row checkbox">
  <div class="offset-sm-2 col-sm-10">
  </div>
</div>
<div class="form-group row">
  <div class="col-xs-3 col-sm-2 form-control-label">Database</div>
  <div class="col-xs-9 col-sm-10">
    <span class="dtButtons btn-group">
      <label class="btn btn-secondary" for="dbMySQL"><input type="radio" id="dbMySQL" name="type" value="mysql" checked>MySQL</label>
      <label class="btn btn-secondary" for="dbSQLite"><input type="radio" id="dbSQLite" name="type" value="sqlite">SQLite</label>
    </span>
    <span class="dtButtons btn-group">
      <label class="btn btn-secondary" for="dbNodb"><input type="checkbox" id="dbNodb" name="nodb"> Skip DB Check</label>
    </span>
    <span class="dtButtons btn-group">
      <label class="btn btn-outline-danger" for="dbDelS"><input type="checkbox" id="dbDelS" name="delete"> Delete Server</label>
    </span>
  </div>
</div>
<div class="row checkbox">
  <div class="offset-sm-2 col-sm-10">
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbHost">Host/IP(:port)</label>
  <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbHostU"><input type="checkbox" id="dbHostU" name="update[]" value="host">Change</label></span>
      <input class="form-control" type="text" id="dbHost" name="host" placeholder="127.0.0.1">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbUser">Username</label>
  <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbUserU"><input type="checkbox" id="dbUserU" name="update[]" value="user">Change</label></span>
      <input class="form-control" type="text" id="dbUser" name="user" placeholder="Username">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbPass">Password</label>
  <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbPassU"><input type="checkbox" id="dbPassU" name="update[]" value="pass">Change</label></span>
      <input class="form-control" type="password" id="dbPass" name="pass" placeholder="Password">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbData">Database</label>
  <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbDataU"><input type="checkbox" id="dbDataU" name="update[]" value="data">Change</label></span>
      <input class="form-control" type="text" id="dbData" name="data" placeholder="CoreProtect">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbPath">Path</label>
    <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbPathU"><input type="checkbox" id="dbPathU" name="update[]" value="path">Change</label></span>
      <input class="form-control" type="text" id="dbPath" name="path" value="<?php echo __DIR__ ?>">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbPrefix">Prefix</label>
  <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbPrefixU"><input type="checkbox" id="dbPrefixU" name="update[]" value="prefix">Change</label></span>
      <input class="form-control" type="text" id="dbPrefix" name="prefix" placeholder="co_" value="co_" required>
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbDmURL">Dynmap URL</label>
    <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbDmURLC"><input type="checkbox" id="dbDmURLC" name="update[]" value="dynmapURL">Change</label></span>
      <input class="form-control" type="text" id="dbDmURL" name="dynmapURL" placeholder="http://127.0.0.1:8123/, empty for no Dynmap">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbDmZoom">Dynmap Zoom</label>
    <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbDmZoomC"><input type="checkbox" id="dbDmZoomC" name="update[]" value="dynmapZoom">Change</label></span>
      <input class="form-control" type="number" id="dbDmZoom" name="dynmapZoom" placeholder="Good value: 6">
    </div>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="dbDmMap">Dynmap Map</label>
    <div class="col-sm-10">
    <div class="input-group">
      <span class="dtButtons updateButton input-group-btn"><label class="btn btn-secondary" for="dbDmMapC"><input type="checkbox" id="dbDmMapC" name="update[]" value="dynmapMap">Change</label></span>
      <input class="form-control" type="text" id="dbDmMap" name="dynmapMap" placeholder="Common maps: flat, surface, or cave">
    </div>
  </div>
</div>
<div class="row">
  <div class="offset-sm-2 col-sm-10">
    <input class="btn btn-secondary" type="submit" id="dbSubmit" value="Submit Server">
  </div>
</div>
</form>
</div>

<div id="setupCfForm" class="card">
<div class="card-header"><span class="h4 card-title">General Configuration</span></div>
<form id="setupCf" class="card-block" role="form" method="post" action="./setup.php?submit=config">
<input class="jsCheck" type="hidden" name="js" value="!disabled">
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfDate">Date Format</label>
  <div class="col-sm-10"><input class="form-control" type="text" id="cfDate" name="dateFormat" placeholder="ll" value="<?php echo $cj['form']['dateFormat'] ?>"></div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfTime">Time Format</label>
  <div class="col-sm-10"><input class="form-control" type="text" id="cfTime" name="timeFormat" placeholder="LTS" value="<?php echo $cj['form']['timeFormat'] ?>"></div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfTDiv">Tab Interval (s)</label>
  <div class="col-sm-10"><input class="form-control" type="number" id="cfTdiv" name="timeDividor" placeholder="300" value="<?php echo $cj['form']['timeDividor'] ?>"></div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfPage">Page Interval</label>
  <div class="col-sm-10"><input class="form-control" type="number" id="cfPage" name="pageInterval" placeholder="25" value="<?php echo $cj['form']['pageInterval'] ?>"></div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfCopy">Item names</label> 
  <div class="col-sm-10">
    <select class="form-control" id="cfCopy" name="bukkitToMc">
      <option value="true"<?php if ($c['flag']['bukkitToMc']) echo " selected";?>>Mincraft item names</option>
      <option value="false"<?php if (!$c['flag']['bukkitToMc']) echo " selected";?>>Bukkit item names</option>
    </select>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-2 form-control-label" for="cfCopy">Copyright</label>
  <div class="col-sm-10"><input class="form-control" type="text" id="cfCopy" name="copyright" placeholder="SimonOrJ, 2015-%year%" value="<?php echo $c['copyright'] ?>"></div>
</div>
<div class="row">
  <div class="offset-sm-2 col-sm-10">
    <input class="btn btn-secondary" type="submit" id="dbSubmit" value="Submit Modification">
  </div>
</div>
</form>
</div>

</div>
<script>
// Remove all "change" buttons (for new server)
a = document.getElementsByClassName("updateButton");
for(var i = 0; i < a.length; i++) a[i].style.display = "none";
a = document.getElementsByClassName("dtButtons");
for(var i = 0; i < a.length; i++) a[i].setAttribute("data-toggle","buttons");
document.getElementById("jsCheck").value="enabled";
</script>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js">// JQuery</script>
<script src="res/js/buttons.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js">// Dropdown</script>
<script src="//cdnjs.cloudflare.com/ajax/libs/tether/1.1.1/js/tether.min.js">// Bootstrap dependency</script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.3/js/bootstrap.min.js" integrity="sha384-ux8v3A6CPtOTqOzMKiuo3d/DomGaaClxFYdCu2HPMBEkf6x2xiDyJ7gkXU0MWwaD" crossorigin="anonymous">// Bootstrap (Alpha!)</script>

<?php endif;
if (empty($_GET["fromLookup"])):?>

</body>
</html>
<?php endif;?>