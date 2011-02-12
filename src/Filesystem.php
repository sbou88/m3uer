<?php

require_once('file_handling.php');

class File {
    public $text = "";
    public $id = "";
    public $iconCls = "";
    public $leaf = true;
    public $expanded = false;
    public $checked = 'undefined';
    public $children = array();

    public function  __construct($id, $text) {
        $this->id = $id;
        $this->text = $text;
    }
}

class Filesystem {
    private $root_path = '';
    private $nodes = array();

    public function  __construct($root_path, $checkboxes = false) {
        $this->root_path = $root_path;
        $this->checkboxes = $checkboxes;
    }
    
    public function add($paths) {
        if (!is_array($paths))
            $paths = array($paths);

        foreach ($paths as $path) {
            if (strpos($path, $this->root_path) === 0)
                $path = substr($path, strlen($this->root_path) + 1);
            $this->add_recursive($this->nodes, explode(DIRECTORY_SEPARATOR, $path));
        }
    }

    private function add_recursive(&$nodes, $items, $relative_path = '.') {
        $key = array_shift($items);
        foreach ($nodes as $node) {
            if ($key == $node->text)
                return $this->add_recursive($node->children, $items, $relative_path.DIRECTORY_SEPARATOR.$key);
        }
        $full_path = simplify_path($this->root_path.DIRECTORY_SEPARATOR.$relative_path.DIRECTORY_SEPARATOR.$key);
        $new_file = new File($full_path, $key);
        $new_file->leaf = !is_dir($full_path);
        $new_file->checked = $this->checkboxes ? false : 'undefined';
        array_push($nodes, $new_file);

        if (count($items) > 0)
            return $this->add_recursive($new_file->children, $items, $relative_path.DIRECTORY_SEPARATOR.$key);
    }

    public function expand($paths) {
        if (!is_array($paths))
            $paths = array($paths);

        $invalid = array();
        foreach ($paths as $path) {
            if (strpos($path, $this->root_path) === 0)
                $path = substr($path, strlen($this->root_path) + 1);
            if (!$this->expand_recursive($this->nodes, explode(DIRECTORY_SEPARATOR, $path)))
                array_push($invalid, $path);
        }
        return $invalid;
    }

    private function expand_recursive(&$nodes, $items, $relative_path = '.') {
        if (count($items) == 0)
            return true;

        $key = array_shift($items);
        foreach ($nodes as $node) {
            if ($key == $node->text) {
                $node->expanded = true;
                if (count($items) == 0) {
                    return true;
                }
                else {
                    return $this->expand_recursive($node->children, $items, $relative_path.DIRECTORY_SEPARATOR.$key);
                }
            }
        }

        return false;
    }

    public function check($paths) {
        if (!is_array($paths))
            $paths = array($paths);

        $invalid = array();
        foreach ($paths as $path) {
            if (strpos($path, $this->root_path) === 0)
                $path = substr($path, strlen($this->root_path) + 1);
            if (!$this->check_recursive($this->nodes, explode(DIRECTORY_SEPARATOR, $path)))
                array_push($invalid, $path);
        }
        return $invalid;
    }

    /*public function check_path($path) {
        /*if (strpos($path, $this->root_path) !== 0)
            return false;
        $stripped_path = substr($path, strlen($this->root_path) + strlen(DIRECTORY_SEPARATOR));
        return $this->valid_path(explode(DIRECTORY_SEPARATOR, $stripped_path));
    }*/

    private function check_recursive(&$nodes, $items, $relative_path = '.') {
        if (count($items) == 0)
            return true;

        $key = array_shift($items);
        foreach ($nodes as $node) {
            if ($key == $node->text) {
                if (count($items) == 0) {
                    $node->checked = true;
                    return true;
                }
                else {
                    return $this->check_recursive($node->children, $items, $relative_path.DIRECTORY_SEPARATOR.$key);
                }
            }
        }

        return false;
    }

    private function valid_path($path) {
        $current_node = &$this->nodes;
        $current_file;
        $node_found;
        foreach ($path as $node_name) {
            $node_found = false;
            foreach ($current_node as $file) {
                if ($file->text == $node_name) {
                    $node_found = true;
                    $current_file = &$file;
                    //$current_file->expanded = true; // TODO: only do this on valid paths
                    $current_node = &$file->children;
                    break;
                }
            }
            if (!$node_found)
                return false;
        }
        $current_file->checked = true;
        return true;
    }

    public function to_json() {
        return json_encode($this->nodes);
    }
}

?>