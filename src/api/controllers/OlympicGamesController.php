<?php

require_once __DIR__ . "/../../config.php";
class OlympicGamesController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = connectDatabase();
    }

    public function index()
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                og.id, 
                og.year, 
                og.city, 
                og.type, 
                og.country_id,
                c.name AS country_name
            FROM olympic_games og
            LEFT JOIN countries c ON og.country_id = c.id
            ORDER BY og.year DESC, og.type ASC
        ");

        $stmt->execute();

        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json(['olympic_games' => $games], 200);
    }
}