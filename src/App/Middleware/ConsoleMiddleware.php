<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use RuntimeException;

class ConsoleMiddleware {
    /*
     * @var \Interop\Container\ContainerInterface
     */

    protected $container;

    /**
     * Constructor
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Called when the class is invoked
     * @param $request
     * @param $response
     * @param $next
     */
    public function __invoke(Request $request, RequestHandler $handler): Response {
        global $argv;
        if ($argv !== null && count($argv) > 1) {
            $class = $argv[1];
            $args = array_slice($argv, 2);            
        } else {
            return $handler->handle($request);
        }
        
        $commandPath = "\\App\\Command\\" . $class;
        $response = new Response();
        
        try {
            if (class_exists($commandPath)) {
                $task = new $commandPath($this->container);

                if (!method_exists($task, 'command')) {
                    throw new \Exception(sprintf('Class %s does not have a command() method', $class));
                }

                $cliResponse = $task->command($args);
                if (!empty($cliResponse)) {
                    $response->getBody()->write($cliResponse);
                }
            } else {
                $response->getBody()->write("Command not found");
            }

            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(500);
        }
    }

}
