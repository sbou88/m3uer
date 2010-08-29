<?php

/*
    TODO:
    * icons depending on filetype
    * store files/playlists in session variaable
    * sort according to filenames
    * add stylesheet
    * cannot handle single quote (see Fool's Garden)
*/

date_default_timezone_set('Europe/Stockholm');
session_start();

require_once('Tree.php');

define('ROOT_DIRECTORY',   '/multimedia');
//define('ROOT_DIRECTORY',   '/share/HDA_DATA/Qmultimedia/Musik');

define('APPLICATION_NAME',          'm3uer');
define('APPLICATION_VERSION',       '0.1.0 unstable');

define('LINE_BREAK',                chr(10));
define('COMMENT_SYMBOL',            '#');

define('SESSION_MUSIC',             '..music');
define('SESSION_PLAYLISTS',         '..playlists');
define('SESSION_TREE',              '..tree');


function playlist_header() {
    return COMMENT_SYMBOL.'Generated by '.APPLICATION_NAME.' v'.APPLICATION_VERSION.', date '.date('Y-m-d (H:i)');
}

function echo_header() {
    echo "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN'\n'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>";
    echo "\n<html><head>";
    echo "\n<title>".APPLICATION_NAME." v.".APPLICATION_VERSION."</title>";
    echo "\n<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />";
    echo "\n<meta http-equiv='Content-Language' content='en' />";
    echo "\n<link rel='stylesheet' href='./style.css' type='text/css' />";
    echo "\n<script type='text/javascript'>";
    echo "\nfunction toggle(id) {
    var wrapper = document.getElementById('wrapper:'+id);
    var image = document.getElementById('image:'+id);
    wrapper.style.display = (wrapper.style.display != 'none' ? 'none' : '' );
    image.src = (wrapper.style.display == 'none' ? './plus.png' : './minus.png');
}";
    echo "\n</script>";
    echo "\n</head><body>";
}

function echo_footer() {
    echo "\n</body></html>";
}

function echo_playlists($reload_session = false) {
    $extensions = array('m3u');

    // load (cached) playlists
    $playlists = null;
    if (!isset($_SESSION[SESSION_PLAYLISTS]) || $reload_session) {
        $playlists = get_files(ROOT_DIRECTORY, $extensions);
        $_SESSION[SESSION_PLAYLISTS] = serialize($playlists);
    }
    else {
        $playlists = unserialize($_SESSION[SESSION_PLAYLISTS]);
    }

    // echo playlists
    echo "<h1>Playlists</h1>";
    echo "<p><ul>";
    foreach ($playlists as $path=>$info) {
        if (isset($info['extension']) && in_array($info['extension'], $extensions)) {
            echo "<li><a href='".basename($_SERVER['PHP_SELF'])."?playlist=$info[path]'>$info[filename]</a></li>";
        }
    }
    echo "</ul></p>";
}

function echo_comments($comments) {
    if (count($comments) > 0) {
        echo "<div class='message' id='comments'><!--<h1>Comments</h1>--><ul><li>".implode('</li><li>', $comments)."</li></ul></div>";
    }
}

function echo_broken_paths($broken) {
    if (count($broken) > 0) {
        echo "<div class='message' id='broken'><h1>Broken paths (will be removed when playlist is updated)</h1><ul><li>".implode('</li><li>', $broken)."</li></ul></div>";
    }
}

function relative($root, $path) {
    $root = path_to_array($root);
    $path = path_to_array($path);

    $relative = array('.');
    if (count($root) <= count($path)) {
        while (count($root) > 0 && $root[0] == $path[0]) {
            array_shift($root);
            array_shift($path);
        }
        foreach ($root as $subdirectory) {
            array_push($relative, '..');
        }
        $relative = array_merge($relative, $path);
    }
    return $relative;
}

function path_to_array($path) {
    $skip_directories = array('', '.');
    $array = explode(DIRECTORY_SEPARATOR, trim($path));
    
    // remove skip directories
    $array = array_diff($array, $skip_directories);
    $array = array_merge($array); // fix indexes

    // remove leading '..' items
    while (count($array) > 0 && $array[0] == '..')
        array_shift($array);

    // remove '..' items within array
    while (($index = array_search('..', $array)))
        array_splice($array, $index - 1, 2);

    $array = array_merge($array); // fix indexes
    return $array;
}

function get_files($path, $extensions) {
    if (!is_dir($path))
        die("\"$path\" is not a directory");

    $directory = opendir($path);
    if (!$directory)
        die("Could not open directory \"$path\"");

    $files = array();

    $files[$path] = pathinfo($path);
    $files[$path]['path'] = $path;

    while (false !== ($file = readdir($directory))) {
        $full_path = $path.DIRECTORY_SEPARATOR.$file;
        $file_info = pathinfo($full_path);
        $file_info['path'] = $full_path;

        if (is_dir($full_path) && !in_array($file, array('.', '..')))
            $files = array_merge($files, get_files($full_path, $extensions));
        elseif (isset($file_info['extension']) && in_array($file_info['extension'], $extensions))
            $files[$full_path] = $file_info;
    }
    closedir($directory);
    return $files;
}





function load_tree($playlist, $reload_session = false) {
    $temp = pathinfo($playlist);
    $root = $temp['dirname'];
    $playlist = $temp['basename'];

    // load (cached) filestructure
    $tree = null;
    if (!isset($_SESSION[SESSION_TREE]) || $reload_session) {
        $tree = new Node();
        $tree->value = DIRECTORY_SEPARATOR;
        load_filesystem($tree, $root, $reload_session);
        $_SESSION[SESSION_TREE] = serialize($tree);
    }
    else {
        $tree = unserialize($_SESSION[SESSION_TREE]);
    }

    load_playlist($tree, $root, $playlist);

    return $tree;
}

function load_filesystem(&$tree, $root, $reload_session = false) {
    $extensions = array('mp3');

    // load (cached) music files
    $music = null;
    if (!isset($_SESSION[SESSION_MUSIC]) || $reload_session) {
        $music = get_files(ROOT_DIRECTORY, $extensions);
        $_SESSION[SESSION_MUSIC] = serialize($music);
    }
    else {
        $music = unserialize($_SESSION[SESSION_MUSIC]);
    }

    foreach ($music as $path=>$info) {
        if (strlen($info['filename']) > 0) {
            $folders = path_to_array(str_replace(ROOT_DIRECTORY.DIRECTORY_SEPARATOR, '', $path));
            $tree->insert($folders, 'path', $info['path']);
            $tree->insert($folders, 'dirname', $info['dirname']);
            $tree->insert($folders, 'basename', $info['basename']);
            $tree->insert($folders, 'filename', $info['filename']);
            $tree->insert($folders, 'exists', true);
        }
    }
}

function load_playlist(&$tree, $root, $playlist) {
    $path = $root.DIRECTORY_SEPARATOR.$playlist;
    $handle = fopen($path, 'r') or die("Error: could not open file '$root".DIRECTORY_SEPARATOR."$playlist' for reading");

    $contents = fread($handle, filesize($path));
    fclose($handle);

    $broken = array();
    $comments = array();
    foreach (explode(LINE_BREAK, $contents) as $line) {
        $line = trim($line);
        if (strlen($line) > 0) {
            if ($line[0] == COMMENT_SYMBOL) {
                array_push($comments, trim(substr($line, 1)));
            }
            else {
                $file = $root.DIRECTORY_SEPARATOR.$line;
                $folders = path_to_array(str_replace(ROOT_DIRECTORY.DIRECTORY_SEPARATOR, '', $file));
                if ($tree->exists($folders))
                    $tree->insert($folders, 'in_playlist', true);
                else
                    array_push($broken, "\"$file\" => \"".implode(DIRECTORY_SEPARATOR, $folders)."\"");
            }
        }
    }
    echo_comments($comments);
    echo_broken_paths($broken);
    
}





function callback_before($node, $level) {
    $indentation = 30;

    $checked = $node->evaluate('in_playlist', true);
    $checked = ($checked ? 'checked' : '');
    if ($node->is_leaf()) {
        // file
        echo "\n".str_repeat('    ', $level)."<div class='file'>";
        echo "\n".str_repeat('    ', $level)."    <img src='./empty.png'>";
        echo "\n".str_repeat('    ', $level)."    <input type='checkbox' name='".$node->value['path']."' value='".$node->value['path']."' id='check:".$node->value['path']."' $checked>";
        echo "\n".str_repeat('    ', $level)."    <label for='check:".$node->value['path']."'>File: ".$node->value['basename']."</label>";
        echo "\n".str_repeat('    ', $level)."</div>";
    }
    else {
        // directory
        echo "\n".str_repeat('    ', $level)."<div class='directory'>";
        echo "\n".str_repeat('    ', $level)."    <img src='./plus.png' id='image:".$node->value['path']."' onClick=\"javascript:toggle('".$node->value['path']."')\">";
        echo "\n".str_repeat('    ', $level)."    <input type='checkbox' name='".$node->value['path']."' value='".$node->value['path']."' id='check:".$node->value['path']."' $checked>";
        echo "\n".str_repeat('    ', $level)."    <label for='check:".$node->value['path']."'>Directory: ".$node->value['basename']."</label>";
        echo "\n".str_repeat('    ', $level)."    <div class='contents' id='wrapper:".$node->value['path']."' style='margin-left:".$indentation."px; display:none;'>";
    }
}

function callback_after($node, $level) {
    if (!$node->is_leaf()) {
        // directory
        echo "\n".str_repeat('    ', $level)."    </div>";
        echo "\n".str_repeat('    ', $level)."</div>";
    }
}


echo_header();

if (isset($_GET['playlist']) && !empty($_GET['playlist'])) {
    if (isset($_POST['update']) && isset($_POST['playlist'])) {
        $path = $_POST['playlist'];
        $temp = pathinfo($path);
        $root = $temp['dirname'];
        $playlist = $temp['basename'];

        // remove all keys not files
        unset($_POST['update']);
        unset($_POST['playlist']);

        // make paths relative to playlists location
        foreach ($_POST as $key=>$value)
            $_POST[$key] = implode(DIRECTORY_SEPARATOR, relative($root, $value));

        // write to file
        $handle = fopen($path, 'w') or die("Error: could not open file '$path' for writing");
        fwrite($handle, playlist_header().LINE_BREAK);
        fwrite($handle, implode(LINE_BREAK, $_POST));
        fclose($handle);

        echo "<div class='message' id='success'><h1>Playlist has been updated</h1></div>";
    }

    $playlist = $_GET['playlist'];
    if (!file_exists($playlist))
        die("Could not locate playlist \"$playlist\"");

    echo "<h1>$playlist</h1>";
    echo "<p><a href='".basename($_SERVER['PHP_SELF'])."'>Back to playlists</a></p>";

    $tree = load_tree($playlist);

    echo "<form method='post' action='".basename($_SERVER['PHP_SELF'])."?playlist=$playlist'>";
    echo "<input type='hidden' name='playlist' value='$playlist'>";
    $tree->iterate('callback_before', 'callback_after', 1);
    echo "<input type='submit' name='update' value='Generate playlist'>";
    echo "<form>";
}
else {
    echo_playlists();
}

echo_footer();

?>