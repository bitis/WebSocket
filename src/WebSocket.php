<?php

class WebSocket
{
    var $master;
    var $sockets = [];
    var $sessions = [];
    var $debug = false;

    function __construct($address, $port)
    {
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("socket_option() failed");
        socket_bind($this->master, $address, $port) or die("socket_bind() failed");
        socket_listen($this->master, 3) or die("socket_listen() failed");

        $this->sockets[] = $this->master;
        $this->say("Server Started : " . date('Y-m-d H:i:s'));
        $this->say("Listening on   : " . $address . " port " . $port);
        $this->say("Master socket  : " . $this->master . "\n");

        while (true) {
            $changed = $this->sockets;
            $write = null;
            $except = null;
            socket_select($changed, $write, $except, NULL);

            foreach ($changed as $socket) {
                if ($socket == $this->master) {
                    $client = socket_accept($this->master);
                    if ($client < 0) {
                        $this->log("socket_accept() failed");
                        continue;
                    } else {
                        $this->connect($client);
                    }
                } else {
                    $bytes = @socket_recv($socket, $buffer, 1024, 0);
                    if ($bytes == 0) {
                        $this->disconnect($socket);
                    } else {
                        $session = $this->sessions[(int)$socket];

                        if (!$session->handshake) {
                            $this->handshake($session, $buffer);
                        } else {
                            $this->process($socket, $this->decode($buffer));
                        }
                    }
                }
            }
        }
    }

    function process($socket, $msg)
    {
        $this->send($socket, $msg);
    }

    function send($client, $msg)
    {
        $msg = $this->encode($msg);
        socket_write($client, $msg, strlen($msg));
    }

    function connect($socket)
    {
        $session = new Session();
        $session->id = uniqid();
        $session->socket = $socket;

        $this->sockets[] = $socket;
        $this->sessions[(int)$socket] = $session;

        $this->log($socket . " CONNECTED!");
        $this->log(date('Y-m-d H:i:s'));
    }

    function disconnect($socket)
    {
        socket_close($socket);

        unset($this->sessions[(int)$socket]);

        $index = array_search($socket, $this->sockets);

        if ($index >= 0) {
            array_splice($this->sockets, $index, 1);
        }

        $this->log($socket . " DISCONNECTED!");
    }

    function handshake($session, $buffer)
    {
        $this->log("\nRequesting handshake...");

        $key = $this->get_sec_key($buffer);
        $accept = $this->calc_accept($key);

        $this->log("Sec-WebSocket-Key: " . $key);
        $this->log("Sec-WebSocket-Accept: " . $accept);

        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: WebSocket\r\n" .
            "Connection: Upgrade\r\n" .
            'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n";

        // 我CNMD，两个'\r\n'收尾

        socket_write($session->socket, $upgrade, strlen($upgrade));

        $session->handshake = true;

        $this->log("Done handshaking...\n");
        return true;
    }

    function calc_accept($key)
    {
        $magic_string = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $hash = sha1($key . $magic_string, true);

        return base64_encode($hash);
    }

    function get_sec_key($req)
    {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) {
            return $match[1];
        }

        return null;
    }

    function say($msg = "")
    {
        echo ">> " . $msg . "\n";
    }

    function reply($msg = '')
    {
        echo "-- " . $msg . "\n\n";
    }

    function log($msg = "")
    {
        if ($this->debug) {
            echo $msg . "\n";
        }
    }

    function decode($buffer) {
        $decoded = '';
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }

        $this->say($decoded);

        return $decoded;
    }

    function encode($msg) {
        $frame = [];
        $frame[0] = '81';
        $len = strlen($msg);
        if ($len < 126) {
            $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
        } else if ($len < 65025) {
            $s = dechex($len);
            $frame[1] = '7e' . str_repeat('0', 4 - strlen($s)) . $s;
        } else {
            $s = dechex($len);
            $frame[1] = '7f' . str_repeat('0', 16 - strlen($s)) . $s;
        }

        $data = '';
        $l = strlen($msg);
        for ($i = 0; $i < $l; $i++) {
            $data .= dechex(ord($msg{$i}));
        }
        $frame[2] = $data;

        $data = implode('', $frame);

        $this->reply($msg);

        return pack("H*", $data);
    }

}

class Session
{
    public $id;
    public $socket;
    public $handshake;
}
