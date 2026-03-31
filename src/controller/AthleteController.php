<?php

class AthleteController
{

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function create(array $data): array
    {
        $errors = $this->validateAthlete($data);
        if (!empty($errors)) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        $stmt = $this->pdo->prepare("
            SELECT id FROM athletes
            WHERE athletes.first_name <=> :first_name
            AND athletes.last_name = :last_name
            AND athletes.birth_date <=> :birth_date 
            LIMIT 1
        ");

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date']
        ]);

        if ($stmt->fetchColumn()){
            return ['status' => 409, 'body' => ['errors' => 'Athlete already exists.']];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id)
            VALUES (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id, :death_date, :death_place, :death_country_id)
        ");

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date'],
            'birth_place' => $data['birth_place'],
            'birth_country_id' => $data['birth_country_id'],
            'death_date' => $data['death_date'],
            'death_place' => $data['death_place'],
            'death_country_id' => $data['death_country_id']
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $athlete = $this->findById($id);
        return ['status' => 201, 'body' => ['athlete' => $athlete]];
    }

    public function update(array $data): array
    {
        $errors = $this->validateAthlete($data);
        if (!empty($errors)) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        $athlete = $this->findById($data['id']);

        if(!$athlete){
            return ['status' => 404, 'body' => ['errors' => 'Athlete does not exist.']];
        }

        $stmt = $this->pdo->prepare("
            UPDATE athletes a
            SET a.first_name = :first_name,
                a.last_name = :last_name,
                a.birth_date = :birth_date,
                a.birth_place = :birth_place,
                a.birth_country_id = :birth_country_id,
                a.death_date = :death_date,
                a.death_place = :death_place,
                a.death_country_id = :death_country_id
            WHERE a.id = :id 
        ");

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date'],
            'birth_place' => $data['birth_place'],
            'birth_country_id' => $data['birth_country_id'],
            'death_date' => $data['death_date'],
            'death_place' => $data['death_place'],
            'death_country_id' => $data['death_country_id'],
            'id' => $data['id']
        ]);


        $athlete = $this->findById($data['id']);
        return ['status' => 200, 'body' => ['athlete' => $athlete]];
    }

    public function delete(int $id): array{

        $athlete = $this->findById($id);
        if(!$athlete){
            return ['status' => 404, 'body' => ['errors' => 'Athlete does not exist.']];
        }

        $stmt = $this->pdo->prepare("
        DELETE FROM athletes WHERE id = :id");

        $stmt->execute([
            'id' => $id
        ]);

        return ['status' => 200];

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

    public function createBulk(array $athletesData): array
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
            return ['status' => 422, 'body' => ['bulk_errors' => $errors]];
        }

        try {
            $this->pdo->beginTransaction();


            $checkStmt = $this->pdo->prepare("
            SELECT id FROM athletes
            WHERE first_name <=> :first_name
            AND last_name = :last_name
            AND birth_date <=> :birth_date 
            LIMIT 1
        ");

            $insertStmt = $this->pdo->prepare("
            INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id)
            VALUES (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id, :death_date, :death_place, :death_country_id)
        ");


            foreach ($athletesData as $index => $data) {

                $checkStmt->execute([
                    'first_name' => $data['first_name'] ?? null,
                    'last_name'  => $data['last_name'],
                    'birth_date' => $data['birth_date'] ?? null
                ]);

                if ($checkStmt->fetchColumn()) {
                    throw new Exception("Športovec {$data['first_name']} {$data['last_name']} už existuje (Riadok v JSON: " . ($index + 1) . ")");
                }

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

                $insertedIds[] = $this->pdo->lastInsertId();
            }

            $this->pdo->commit();

            return [
                'status' => 201,
                'body' => [
                    'message'      => 'Úspešne pridaných ' . count($insertedIds) . ' športovcov.',
                    'inserted_ids' => $insertedIds
                ]
            ];

        } catch (Exception $e) {

            $this->pdo->rollBack();
            return ['status' => 409, 'body' => ['error' => $e->getMessage()]];
        }
    }


}