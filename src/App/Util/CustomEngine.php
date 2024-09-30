<?php

declare(strict_types=1);

namespace App\Util;

use League\Plates\Engine;
use PHPUnit\Framework\MockObject\BadMethodCallException;
use Psr\Http\Message\ResponseInterface;

class CustomEngine {

    private $engine;

    public function __construct($directory = null) {
        $this->engine = new Engine($directory, 'phtml');
    }

    public function fetch($name, array $data = []) {
        $name = str_replace('.phtml', '', $name);
        return $this->engine->render($name, $data);
    }

    public function render(ResponseInterface $response, $name, array $data = []) {
        $name = str_replace('.phtml', '', $name);
        $output = $this->engine->render($name, $data);
        $response->getBody()->write($output);
        return $response;
    }

    public function __call($method, $args) {
        if (!method_exists($this, $method) && method_exists($this->engine, $method)) {
            return call_user_func_array([$this->engine, $method], $args);
        }
        throw new BadMethodCallException("Undefined method $method");
    }

}
