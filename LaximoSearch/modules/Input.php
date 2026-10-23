<?php

namespace LaximoSearch\modules;


class Input
{
    public function getString($arg, $default = '')
    {
        $result = isset($_GET[$arg]) ? (string)$_GET[$arg] : $default;

        return $result;
    }

    public function getFiles($name) {
        return !empty($_FILES[$name]) ? $_FILES[$name] : false;
    }

    public function getInt($arg, $default = 0)
    {
        $result = (int)(!empty($_GET[$arg]) ? $_GET[$arg] : $default);

        return $result;
    }

    public function get($arg)
    {

        return !empty($_GET[$arg]) ? $_GET[$arg] : null;
    }

    public function getArray()
    {

        return (array)$_GET;
    }

    public function formData() {
        return (array) $_POST;
    }

}