<?php

include "../src/WebSocket.php";

class ChatBot extends WebSocket
{
    function process($socket, $msg)
    {
        switch ($msg) {
            case "ping" :
                $this->send($socket, 'pong');
                break;
            case 'hi':
            case "hello" :
                $this->send($socket, "$msg human");
                break;
            case "date"  :
                $this->send($socket, "today is " . date("Y.m.d"));
                break;
            case "time"  :
                $this->send($socket, "server time is " . date("H:i:s"));
                break;
            case "thanks":
                $this->send($socket, "you're welcome");
                break;
            case "bye" :
                $this->send($socket, "bye");
                break;
            default:
                $this->send($socket, $msg . " not understood");
                break;
        }
    }
}

$master = new ChatBot("localhost", 12345);