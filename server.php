<?php
declare(strict_types=1);
use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

use Tqdev\PhpCrudApi\Api as CrudApi;
use Tqdev\PhpCrudApi\Config\Config as CrudConfig;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__.'/vendor/autoload.php';
ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Jakarta');

function clearStaticProperties() {
  $classes = get_declared_classes();
  foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    $statics = $reflection->getProperties(ReflectionProperty::IS_STATIC);
    foreach ($statics as $static) {
      if ($static->isPublic()) {
        $static->setValue(null);
      }
    }
  }
}

$host_api = getenv('SERVER_ACCESS_IP');
$host_port =(int) getenv('SERVER_ACCESS_PORT');

global $apiConfig;
$apiConfig = new CrudConfig([
  'driver'      => getenv('DRIVER'),
  'address'     => getenv('MYSQL_HOST'),
  'port'        =>(int) getenv('MYSQL_PORT'),
  'database'    => getenv('MYSQL_DATABASE'),
  'username'    => getenv('MYSQL_USER'),
  'password'    => getenv('MYSQL_PASSWORD'),
  'basePath'    => '/api',
  'middlewares'  => 'cors,xml,json'
]);

global $api;
$api = new CrudApi($apiConfig);

$server = new Server($host_api,$host_port,SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
$server->set([
   'worker_num' =>(int) getenv('NUM_PROSES'), // Sesuaikan dengan jumlah CPU
   'daemonize' => false,
   'max_request' => 1000,
   'max_conn' => 1024,
   'enable_coroutine' => true,
   'max_coroutine' => 3000,
   'hook_flags' => SWOOLE_HOOK_ALL,
   'buffer_output_size' => 32 * 1024 * 1024, // 32MB
   'socket_buffer_size' => 128 * 1024 * 1024, // 128MB
]);

$server->on('ManagerStart', function(Server $server)use($host_api,$host_port){
  echo "Swoole server manager started at http://$host_api:".getenv('SERVER_ACCESS_PORT')."\n";
});

$server->on("WorkerStart", function($server, $workerId){
  echo "Worker Started: $workerId\n";
  if (function_exists('opcache_reset')) {
    opcache_reset();
  }
});

$server->on("Shutdown", function($server, $workerId){
  echo "Server shutting down...\n";
});

$server->on("WorkerStop", function($server, $workerId){
  echo "Worker Stopped: $workerId\n";
});
 
$server->on('workerExit', function($server, $workerId) {
  clearStaticProperties();
});
 
$server->on('Request', $handleRequest);

$handleRequest = function(Request $request, Response $response){
  global $api;
  try {
    $queryString = $request->server['query_string'] ?? '';
    $baseUri = 'http://localhost:'.getenv('SERVER_ACCESS_PORT').$request->server['request_uri'];
    $queryString = $request->server['query_string'] ?? '';
    if (!empty($queryString)) {
      // Cek apakah request_uri sudah mengandung query string
      if (strpos($baseUri, '?') === false) {
        $baseUri .= '?' . $queryString;
      }
    }
    $psr17Factory = new Psr17Factory();
    $myrequest = $psr17Factory->createServerRequest($request->server['request_method'], $baseUri);
    //echo "===================START REQUEST===================\n";
    //var_dump($myrequest);
    //echo "===================END REQUEST===================\n";
    $myresponse = $api->handle($myrequest);
    $status = $myresponse->getStatusCode();
    $headers = $myresponse->getHeaders();
    $myresponse->getBody()->rewind();
    $body = $myresponse->getBody()->getContents();
    $response->status($status);
    foreach($headers as $key => $values){
      foreach($values as $value){
        $response->header($key,$value);
      }
    }
    $response->end($body);
  } catch (Throwable $e){
    $errorString = "Internal Server Error: " . $e->getMessage();
    echo $errorString."\n";
    $response->status(500);
    $response->end($errorString);
  } finally {
    if (gc_enabled()) {
      gc_collect_cycles();
    }
  }
}
$server->start();
