<?php

require_once('config.php');
require_once('file_handling.php');
require_once('Filesystem.php');

if (!isset($_GET['q']))
    die("No valid query given");

switch ($_GET['q']) {
    case 'create':
        create();
        break;
    case 'delete':
        delete();
        break;
    case 'save':
        save();
        break;
    default:
        die("Unrecognized query $_GET[q]");
}

////////////////////////////////////////////////////////////////////////////////
//   QUERIES   /////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

function create() {
    if (isset($_GET['root']) && isset($_GET['name']) && isset($_GET['path'])) {
        $root = $_GET['root'];
        $path = $_GET['path'];
        $name = $_GET['name'];

        $playlist_path = $path.DIRECTORY_SEPARATOR.$name;
        $playlist_file_info = pathinfo($playlist_path);

        //~ die("root: $root, path: $path, name: $name<br><pre>".print_r($playlist_file_info, true));

        $extension = isset($playlist_file_info['extension']) ? $playlist_file_info['extension'] : '';
        if (!in_array($extension, explode(',', PLAYLIST_FORMATS)))
            die('Could not create playlist: Invalid file extension');

        // Create file
        @touch($playlist_path)
            or die('Could not create playlist: Operation not permitted');

        // Add new file to session
        $playlists = unserialize($_SESSION[SESSION_PLAYLISTS]);
        $relative_path = make_relative_path($root, $playlist_path, false);
        $playlists->add($relative_path);
        $_SESSION[SESSION_PLAYLISTS] = serialize($playlists);

        //~ die("<pre>".print_r(json_decode($playlists->to_json()), true)."</pre>");

        echo 'Playlist created successfully';
    }
    else
        die("Could not create playlist: Invalid aguments given");
}

function delete() {
    if (isset($_GET['root']) && isset($_GET['path'])) {
        $root = $_GET['root'];
        $path = $_GET['path'];

        $playlist_file_info = pathinfo($path);

        //~ die("root: $root, path: $path<br><pre>".print_r($playlist_file_info, true));

        if (!is_file($path))
            die('Could not delete playlist: Not a file');

        $extension = isset($playlist_file_info['extension']) ? $playlist_file_info['extension'] : '';
        if (!in_array($extension, explode(',', PLAYLIST_FORMATS)))
            die('Could not delete playlist: Invalid file extension');

        // Delete file
        @unlink($path)
            or die('Could not delete playlist: Operation not permitted');

        // Remove file from session
        $playlists = unserialize($_SESSION[SESSION_PLAYLISTS]);
        $relative_path = make_relative_path($root, $path, false);
        $playlists->remove($relative_path);
        $_SESSION[SESSION_PLAYLISTS] = serialize($playlists);

        //~ die("<pre>".print_r(json_decode($playlists->to_json()), true)."</pre>");

        echo 'Playlist deleted successfully';
    }
    else
        die('Could not delete playlist: Invalid aguments given');
}

function save() {
    if (isset($_GET['root']) && isset($_GET['path']) && isset($_POST['data'])) {
        $playlist_file_info = get_file_info($_GET['path']);

        // Reference: http://stackoverflow.com/questions/689185/json-decode-returns-null-php
        if (get_magic_quotes_gpc()) {
            // Remove PHP magic quotes 
            $data = stripslashes($_POST['data']);
        }
        else {
            $data = $_POST['data'];
        }
        $data = json_decode($data, true);

        if ($data == null)
            die('Playlist could not be saved: Could not parse json data');

        //~ die("<pre>".print_r($data, true)."</pre>");

        $handle = fopen($playlist_file_info['path'], 'w')
            or die('Playlist could not be saved: Could not open file for writing');

        fwrite($handle, playlist_header().LINE_BREAK);
        fwrite($handle, implode("\n", playlist_contents($playlist_file_info['path'], $data)));
        fclose($handle);

        echo 'Playlist saved successfully';
    }
    else
        die('Playlist could not be saved: Invalid aguments given');
}

////////////////////////////////////////////////////////////////////////////////
//   HELPERS   /////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

function playlist_header() {
    return COMMENT_SYMBOL.'Generated by '.APPLICATION_NAME.' v'.APPLICATION_VERSION.', date '.date('Y-m-d (H:i)');
}

function playlist_contents($playlist_path, $data) {
    // Make data paths relative to playlist path, and skip directories
    $playlist_content = array();
    foreach ($data as $file) {
        if (file_exists($file) && !is_dir($file))
            array_push($playlist_content, make_relative_path($playlist_path, $file));
    }
    return $playlist_content;
}

?>