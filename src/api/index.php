<?php
require_once __DIR__ . "/Router.php";
require_once __DIR__ . "/Response.php";
require_once __DIR__ . "/../config.php";

require_once __DIR__ . "/controllers/PlacementController.php";
require_once __DIR__ . "/controllers/AthleteController.php";
require_once __DIR__ . "/controllers/UserController.php";
require_once __DIR__ . "/controllers/AuthController.php";
require_once __DIR__ . "/controllers/ImportController.php";
require_once __DIR__ . "/controllers/CountriesController.php";
require_once __DIR__ . "/controllers/DisciplinesController.php";
require_once __DIR__ . "/controllers/OlympicGamesController.php";


header("Content-Type: application/json");
setCorsHeaders();

$router = new Router();
$authMiddleware = ["AuthMiddleware", "requireAuth"];

$router->get("/placements/{id}", ["PlacementController", "getAthletePlacements"]);
$router->get("/placements", ["PlacementController", "index"]);
$router->post("/placements", ["PlacementController", "create"], [$authMiddleware]);
$router->put("/placements/{id}", ["PlacementController", "update"], [$authMiddleware]);
$router->delete("/placements/{id}", ["PlacementController", "delete"], [$authMiddleware]);

$router->get("/athletes", ["AthleteController", "index"]);
$router->get("/athletes/{id}", ["AthleteController", "show"]);
$router->post("/athletes", ["AthleteController", "create"], [$authMiddleware]);
$router->put("/athletes/{id}", ["AthleteController", "update"], [$authMiddleware]);
$router->delete("/athletes/{id}", ["AthleteController", "delete"], [$authMiddleware]);

$router->get("/users/me", ["UserController", "showProfile"], [$authMiddleware]);
$router->put("/users/me", ["UserController", "updateProfile"], [$authMiddleware]);
$router->put("/users/me/password", ["UserController", "updatePassword"], [$authMiddleware]);
$router->get("/users/me/logins", ["UserController", "getLoginHistory"], [$authMiddleware]);

$router->post("/auth/register", ["AuthController", "register"]);
$router->post("/auth/login", ["AuthController", "login"]);
$router->get("/auth/google/callback", ["AuthController", "googleCallback"]);

$router->post("/import", ["ImportController", "import"], [$authMiddleware]);
$router->delete("/import", ["ImportController", "clear"], [$authMiddleware]);

$router->get("/countries", ["CountriesController", "index"]);
$router->get("/disciplines", ["DisciplinesController", "index"]);
$router->get("/olympic_games", ["OlympicGamesController", "index"]);


$router->run();