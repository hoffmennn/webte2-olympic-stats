<?php

require_once __DIR__ . "/../../config.php";
class CountriesController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = connectDatabase();
    }


    public function index()
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM countries ORDER BY name ASC");
        $stmt->execute();

        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json(['countries' => $countries], 200);
    }

    /**
     * Detail jednej krajiny (ak by bolo treba)
     */
    public function show(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM countries WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $country = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$country) {
            return Response::json(["error" => "Country not found"], 404);
        }

        return Response::json(['country' => $country], 200);
    }
}