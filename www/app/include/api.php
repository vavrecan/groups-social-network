<?php

class API
{
    private $server;
    private $session;

    public function __construct($server) {
        $this->server = $server;
    }

    public function login($session) {
        $this->session = $session;
        $result = $this->call("/user/me", array("image_format" => "p50x50", "time_format" => "day"), false);
        return $result;
    }

    public function call($path, $params = array(), $throwException = true) {
        if ($this->session != null)
            $params["session"] = $this->session;

        if (is_string($this->server)) {
            $fields = http_build_query($params);

            //open connection
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $this->server . $path);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // forward IP
            if (isset($_SERVER["REMOTE_ADDR"]))
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("X_FORWARDED_FOR: " . $_SERVER["REMOTE_ADDR"]));

            //execute post
            $rawResponse = curl_exec($ch);

            if (strlen($rawResponse) == 0)
                throw new Exception("Server returned no response");

            $result = json_decode($rawResponse, true);

            if (json_last_error() > 0) {
                throw new Exception("Unable to parse: {$rawResponse}");
                $result = array();
            }

            //close connection
            curl_close($ch);

            if ($throwException && isset($result["error"]))
                throw new Exception($result["error"]["message"]);

            return $result;
        }
        else {
            throw new Exception("Invalid API configuration");
        }
    }

    public function callUpload($path, $params, $fileKey, $file) {
        if ($this->session != null)
            $params["session"] = $this->session;

        if (is_string($this->server)) {
            //open connection
            $ch = curl_init();

            // escape this curl security buggy shit
            foreach ($params as &$param)
                $param = ltrim($param, "@");

            // add file
            if (strlen(trim($file)) > 0)
                $params[$fileKey] = "@". $file;

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $this->server . $path);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            if (isset($_SERVER["REMOTE_ADDR"]))
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("X_FORWARDED_FOR: " . $_SERVER["REMOTE_ADDR"]));

            //execute post
            $rawResponse = curl_exec($ch);

            if (strlen($rawResponse) == 0)
                throw new Exception("Server returned no response");

            $result = json_decode($rawResponse, true);

            if (json_last_error() > 0) {
                throw new Exception("Unable to parse {$rawResponse}");
                $result = array();
            }

            //close connection
            curl_close($ch);
            return $result;
        }
        else {
            throw new Exception("Invalid API configuration");
        }
    }
}