<?php

require_once __DIR__ . "/../../config.php";

class DisciplinesController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = connectDatabase();
    }


    public function index()
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM disciplines ORDER BY name ASC");
        $stmt->execute();

        $disciplines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json(['disciplines' => $disciplines], 200);
    }


    public function create()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || empty(trim($data['name']))) {
            return Response::json(['errors' => ['name' => 'Názov disciplíny je povinný']], 422);
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO disciplines (name) VALUES (:name)");
            $stmt->execute(['name' => $data['name']]);

            $id = $this->pdo->lastInsertId();

            return Response::json([
                'id' => $id,
                'name' => $data['name'],
                'message' => 'Disciplína vytvorená'
            ], 201);
        } catch (Exception $e) {
            return Response::json(['error' => $e->getMessage()], 409);
        }
    }
}