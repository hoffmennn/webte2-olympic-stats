<?php

class Router {

    private $routes = [];


    public function add($method, $route, $handler, $middleware = [])
    {
        $this->routes[] = [
            "method" => $method,
            "route" => $route,
            "handler" => $handler,
            "middleware" => $middleware
        ];
    }

    public function get($route, $handler, $middleware = []){ $this->add("GET", $route, $handler, $middleware); }
    public function post($route, $handler, $middleware = []){ $this->add("POST", $route, $handler, $middleware); }
    public function put($route, $handler, $middleware = []){ $this->add("PUT", $route, $handler, $middleware); }
    public function delete($route, $handler, $middleware = []){ $this->add("DELETE", $route, $handler, $middleware); }

    public function run()
    {
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $uri = preg_replace("#^/api#", "", $uri);

        foreach ($this->routes as $route) {

            if ($route["method"] !== $method) {
                continue;
            }

            $pattern = preg_replace("#\{[a-zA-Z]+\}#", "([^/]+)", $route["route"]);
            $pattern = "#^".$pattern."$#";

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);


                if (!empty($route["middleware"])) {
                    foreach ($route["middleware"] as $middleware) {
                        call_user_func($middleware);
                    }
                }


                [$class, $function] = $route["handler"];
                require_once __DIR__ . "/controllers/" . $class . ".php";
                $controller = new $class;

                return call_user_func_array([$controller, $function], $matches);
            }
        }

        Response::json(["error" => "Not Found"], 404);
    }
}