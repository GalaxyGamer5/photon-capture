<?php
// reproduce_issue.php
// Mock POST request to auth.php

$_SERVER['REQUEST_METHOD'] = 'POST';
$input = json_encode(['password' => 'photon2024']);

// Mock file_get_contents('php://input') by using a stream wrapper or just modifying auth.php temporarily?
// Actually, it's hard to mock php://input in a CLI script without external tools or modifying the script.
// A better way is to use curl or just modify auth.php to accept a variable if defined.
// OR, we can use a wrapper.

// Let's try to use a simple php script that includes auth.php but we need to mock php://input.
// One way is to use stream_wrapper_unregister and register.

class VarStream {
    private $string;
    private $position;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->string = $GLOBALS['mock_input'];
        $this->position = 0;
        return true;
    }

    public function stream_read($count) {
        $ret = substr($this->string, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->string);
    }

    public function stream_stat() {
        return [];
    }
}

$GLOBALS['mock_input'] = json_encode(['password' => 'photon2024']);

stream_wrapper_unregister("php");
stream_wrapper_register("php", "VarStream");

require 'admin/api/auth.php';
