<?php
// Minimal CLI to insert Constitution article and menu item using mysqli
$host = getenv('JOOMLA_DB_HOST') ?: 'db';
$db   = getenv('JOOMLA_DB_NAME') ?: 'joomla';
$user = getenv('JOOMLA_DB_USER') ?: 'joomla';
$pass = getenv('JOOMLA_DB_PASSWORD') ?: 'joomla';

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    fwrite(STDERR, 'DB connect error: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$alias = 'constitution';
$title = 'Канстытуцыя Новай Беларусі';
$now   = date('Y-m-d H:i:s');

$mysqli->begin_transaction();

// Resolve existing article
$aid = 0;
if ($res = $mysqli->query("SELECT id FROM jo8cq_content WHERE alias='".$mysqli->real_escape_string($alias)."' LIMIT 1")) {
    if ($row = $res->fetch_assoc()) { $aid = (int)$row['id']; }
}

if ($aid === 0) {
    $introtext = '';
    $fulltext  = '<h4>Артыкул 1. Рэспубліка Беларусь</h4>';
    $state     = 1;
    $catid     = 2;
    $created_by = 585;
    $created_by_alias = '';
    $modified_by = 585;
    $images = '';
    $urls   = '';
    $attribs = '';
    $version = 1;
    $ordering = 0;
    $metakey = '';
    $metadesc = '';
    $access = 1;
    $hits   = 0;
    $metadata = '';
    $featured = 0;
    $language = '*';
    $note = '';

    $q = "INSERT INTO jo8cq_content (asset_id,title,alias,introtext,`fulltext`,state,catid,created,created_by,created_by_alias,modified,modified_by,images,urls,attribs,version,ordering,metakey,metadesc,access,hits,metadata,featured,language,note) VALUES (0,'".
        $mysqli->real_escape_string($title)."','".$mysqli->real_escape_string($alias)."','".$mysqli->real_escape_string($introtext)."','".$mysqli->real_escape_string($fulltext)."',".
        (int)$state.",".(int)$catid.",'".$now."',".(int)$created_by.",'".$mysqli->real_escape_string($created_by_alias)."','".$now."',".(int)$modified_by.",'".
        $mysqli->real_escape_string($images)."','".$mysqli->real_escape_string($urls)."','".$mysqli->real_escape_string($attribs)."',".(int)$version.",".(int)$ordering.",'".$mysqli->real_escape_string($metakey)."','".$mysqli->real_escape_string($metadesc)."',".(int)$access.",".(int)$hits.",'".$mysqli->real_escape_string($metadata)."',".(int)$featured.",'".$mysqli->real_escape_string($language)."','".$mysqli->real_escape_string($note)."')";
    if (!$mysqli->query($q)) { throw new Exception('Insert content failed: ' . $mysqli->error); }
    $aid = (int)$mysqli->insert_id;
}

if ($aid <= 0) { throw new Exception('No article id'); }

// com_content extension id
$cc = 19;
if ($res = $mysqli->query("SELECT extension_id FROM jo8cq_extensions WHERE element='com_content' AND type='component' LIMIT 1")) {
    if ($row = $res->fetch_assoc()) { $cc = (int) $row['extension_id']; }
}

// Ensure menu item exists
$exists = 0;
if ($res = $mysqli->query("SELECT COUNT(*) AS c FROM jo8cq_menu WHERE menutype='mainmenu' AND alias='constitution'")) {
    if ($row = $res->fetch_assoc()) { $exists = (int) $row['c']; }
}
if ($exists === 0) {
    $m = 0;
    if ($res = $mysqli->query("SELECT COALESCE(MAX(rgt),0) AS m FROM jo8cq_menu WHERE menutype='mainmenu'")) {
        if ($row = $res->fetch_assoc()) { $m = (int) $row['m']; }
    }
    $lft = $m + 1; $rgt = $m + 2;
    $link = 'index.php?option=com_content&view=article&id=' . $aid;
    $q = "INSERT INTO jo8cq_menu (menutype,title,alias,note,path,link,type,published,parent_id,level,component_id,checked_out,checked_out_time,browserNav,access,img,template_style_id,params,lft,rgt,home,language,client_id) VALUES ('mainmenu','Канстытуцыя','constitution','', 'constitution', '".$mysqli->real_escape_string($link)."', 'component', 1, 1, 1, ".(int)$cc.", NULL, NULL, 0, 1, '', 0, '', ".(int)$lft.", ".(int)$rgt.", 0, '*', 0)";
    if (!$mysqli->query($q)) { throw new Exception('Insert menu failed: ' . $mysqli->error); }
}

$mysqli->commit();
echo "OK\n";

