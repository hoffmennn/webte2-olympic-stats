<?php

require_once __DIR__ . "/../../config.php";


class AthleteController
{
    private PDO $pdo;


    public function __construct()
    {
        $this->pdo = connectDatabase();
    }

    public function show(int $id)
    {
        $athlete = $this->findById($id);
        if ($athlete === null) {
            return Response::json(["error" => "Athlete not found"], 404);
        }
        return Response::json(['athlete' => $athlete], 200);
    }

    public function index()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM athletes ORDER BY last_name ASC");
        $stmt->execute();
        $athletes = $stmt->fetchAll();
        return Response::json(["athletes" => $athletes], 200);
    }

    public function create()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data[0]) && is_array($data[0])) {
            $this->createBulk($data);
            return;
        }

        $errors = $this->validateAthlete($data);
        if (!empty($errors)) {
            Response::json(['errors' => $errors], 422);
        }

        try {
            $id = $this->performInsert($data);
            $athlete = $this->findById($id);
            Response::json(['athlete' => $athlete], 201);
        } catch (Exception $e) {
            Response::json(['errors' => $e->getMessage()], 409);
        }
    }

    public function createBulk(array $athletesData)
    {
        $errors = [];
        $insertedIds = [];


        foreach ($athletesData as $index => $data) {
            $validationErrors = $this->validateAthlete($data);
            if (!empty($validationErrors)) {
                $errors[] = ['row' => $index + 1, 'errors' => $validationErrors];
            }
        }

        if (!empty($errors)) {
            Response::json(['bulk_errors' => $errors], 422);
        }

        try {
            $this->pdo->beginTransaction();
            foreach ($athletesData as $data) {
                $insertedIds[] = $this->performInsert($data);
            }
            $this->pdo->commit();

            Response::json([
                'message' => 'Úspešne pridaných ' . count($insertedIds) . ' športovcov.',
                'inserted_ids' => $insertedIds
            ], 201);

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['error' => $e->getMessage()], 409);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $errors = $this->validateAthlete($data);
        if (!empty($errors)) {
            Response::json(['errors' => $errors], 422);
        }

        $athlete = $this->findById($id);
        if(!$athlete){
            Response::json(['errors' => 'Athlete does not exist.'], 404);
        }

        $stmt = $this->pdo->prepare("
            UPDATE athletes
            SET first_name = :first_name,
                last_name = :last_name,
                birth_date = :birth_date,
                birth_place = :birth_place,
                birth_country_id = :birth_country_id,
                death_date = :death_date,
                death_place = :death_place,
                death_country_id = :death_country_id
            WHERE id = :id 
        ");

        $stmt->execute([
            'first_name'       => $data['first_name'] ?? null,
            'last_name'        => $data['last_name'],
            'birth_date'       => $data['birth_date'] ?? null,
            'birth_place'      => $data['birth_place'] ?? null,
            'birth_country_id' => $data['birth_country_id'] ?? null,
            'death_date'       => $data['death_date'] ?? null,
            'death_place'      => $data['death_place'] ?? null,
            'death_country_id' => $data['death_country_id'] ?? null,
            'id'               => $id // Pouzivame ID z URL, nie z body
        ]);

        $updatedAthlete = $this->findById($id);
        Response::json(['athlete' => $updatedAthlete], 200);
    }

    public function delete($id)
    {
        $athlete = $this->findById($id);
        if(!$athlete){
            Response::json(['errors' => 'Athlete does not exist.'], 404);
        }

        $stmt = $this->pdo->prepare("DELETE FROM athletes WHERE id = :id");
        $stmt->execute(['id' => $id]);

        Response::json(null, 204);
    }


    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT 
        a.id,
        a.first_name,
        a.last_name,
        a.birth_date,
        a.birth_place,
        a.birth_country_id,
        bc.name AS birth_country,
        a.death_date,
        a.death_place,
        a.death_country_id,
        dc.name AS death_country
        FROM athletes a
        LEFT JOIN countries bc
        ON a.birth_country_id = bc.id
        LEFT JOIN countries dc
        ON a.death_country_id = dc.id
        WHERE a.id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function validateAthlete(array $data): array
    {
        $errors = [];

        if (empty($data['last_name'])) {
            $errors[] = 'Priezvisko je povinné';
        } elseif (mb_strlen($data['last_name']) > 100) {
            $errors[] = 'Priezvisko môže mať max. 100 znakov';
        }

        if (isset($data['first_name']) && mb_strlen($data['first_name']) > 100) {
            $errors[] = 'Meno môže mať max. 100 znakov';
        }

        if (!empty($data['birth_date']) && !$this->isValidDate($data['birth_date'])) {
            $errors[] = 'Dátum narodenia musí byť vo formáte YYYY-MM-DD';
        }

        if (!empty($data['death_date']) && !$this->isValidDate($data['death_date'])) {
            $errors[] = 'Dátum úmrtia musí byť vo formáte YYYY-MM-DD';
        }

        if (!empty($data['birth_country_id']) && !$this->countryExists((int) $data['birth_country_id'])) {
            $errors[] = 'Krajina narodenia neexistuje';
        }

        if (!empty($data['death_country_id']) && !$this->countryExists((int) $data['death_country_id'])) {
            $errors[] = 'Krajina úmrtia neexistuje';
        }

        return $errors;
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function countryExists(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }


    private function performInsert(array $data): int
    {
        $checkStmt = $this->pdo->prepare("
        SELECT id FROM athletes
        WHERE first_name <=> :first_name
        AND last_name = :last_name
        AND birth_date <=> :birth_date 
        LIMIT 1
    ");

        $checkStmt->execute([
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'],
            'birth_date' => $data['birth_date'] ?? null
        ]);

        if ($checkStmt->fetchColumn()) {
            throw new Exception("Športovec {$data['first_name']} {$data['last_name']} už existuje.");
        }


        $insertStmt = $this->pdo->prepare("
        INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id)
        VALUES (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id, :death_date, :death_place, :death_country_id)
    ");

        $insertStmt->execute([
            'first_name'       => $data['first_name'] ?? null,
            'last_name'        => $data['last_name'],
            'birth_date'       => $data['birth_date'] ?? null,
            'birth_place'      => $data['birth_place'] ?? null,
            'birth_country_id' => $data['birth_country_id'] ?? null,
            'death_date'       => $data['death_date'] ?? null,
            'death_place'      => $data['death_place'] ?? null,
            'death_country_id' => $data['death_country_id'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }




}