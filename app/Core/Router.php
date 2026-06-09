<?php

namespace App\Core;

class Router {
    private $routes = [];

    public function add(string $action, string $controller, string $method) {
        $this->routes[$action] = ['controller' => $controller, 'method' => $method];
    }

    public function handleRequest(string $action) {
        if (array_key_exists($action, $this->routes)) {
            $controllerName = "App\\Controllers\\" . $this->routes[$action]['controller'];
            $methodName = $this->routes[$action]['method'];

            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                if (method_exists($controller, $methodName)) {
                    $controller->$methodName();
                    return;
                }
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Unknown action.']);
    }
}
