<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require 'NotificationServer.php';

$server = IoServer::factory(
  new HttpServer(
    new WsServer(
      new NotificationServer()
    )
  ),
  8080
);

$server->run();
