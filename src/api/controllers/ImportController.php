<?php

class ImportController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = connectDatabase();
        // Ochrana: Import a mazanie môže robiť len prihlásený používateľ
        AuthMiddleware::requireAuth();
    }

    /**
     * POST /api/import
     * Spracovanie nahraného CSV súboru
     */
    public function import()
    {
        if (!isset($_FILES['csv_file'])) {
            Response::json(['error' => 'Súbor nebol nahraný'], 400);
        }

        $file = $_FILES['csv_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (strtolower($ext) !== 'csv') {
            Response::json(['error' => 'Povolené sú iba CSV súbory'], 400);
        }

        if ($file['error'] !== 0) {
            Response::json(['error' => "Chyba pri nahrávaní súboru (kód: {$file['error']})"], 400);
        }

        try {
            $stats = $this->importCsvToDatabase($file['tmp_name']);
            Response::json([
                'message'  => 'Import dokončený',
                'inserted' => $stats['inserted'],
                'skipped'  => $stats['skipped'],
                'errors'   => $stats['errors'],
            ]);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/import
     * Vymazanie olympijských dát (okrem používateľov)
     */
    public function clear()
    {
        try {
            // Poradie je dôležité kvôli Constraintom (FK)
            $this->pdo->exec("DELETE FROM placements");
            $this->pdo->exec("DELETE FROM disciplines");
            $this->pdo->exec("DELETE FROM olympic_games");
            $this->pdo->exec("DELETE FROM athletes");
            $this->pdo->exec("DELETE FROM countries");

            Response::json(['message' => 'Všetky dáta boli úspešne vymazané']);
        } catch (Exception $e) {
            Response::json(['error' => 'Chyba pri mazaní dát: ' . $e->getMessage()], 500);
        }
    }

    // ============================================================
    // PRIVÁTNE POMOCNÉ METÓDY (Pôvodná logika z import.php)
    // ============================================================

    private function importCsvToDatabase(string $filePath): array
    {
        $stats = ['inserted' => 0, 'skipped' => 0, 'errors' => []];
        $rows = $this->parseCsv($filePath);

        if (empty($rows)) {
            throw new Exception("CSV súbor je prázdny alebo neplatný.");
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                try {
                    $birthCountryId = !empty($row['birth_country']) ? $this->getOrCreateCountry($row['birth_country']) : null;
                    $deathCountryId = !empty($row['death_country']) ? $this->getOrCreateCountry($row['death_country']) : null;
                    $ohCountryId    = $this->getOrCreateCountry($row['oh_country']);

                    $gamesId = $this->getOrCreateGames(
                        (int)$row['oh_year'],
                        $row['oh_type'],
                        $row['oh_city'],
                        $ohCountryId
                    );

                    $athleteId = $this->getOrCreateAthlete(
                        $row['name'],
                        $row['surname'],
                        $this->normalizeDate($row['birth_day']),
                        $row['birth_place'] ?: null,
                        $birthCountryId,
                        $this->normalizeDate($row['death_day']),
                        $row['death_place'] ?: null,
                        $deathCountryId
                    );

                    $disciplineId = $this->getOrCreateDiscipline($row['discipline']);

                    $this->insertPlacement($athleteId, $gamesId, $disciplineId, $row['placing']);

                    $stats['inserted']++;
                } catch (Exception $e) {
                    $stats['skipped']++;
                    $stats['errors'][] = "Riadok " . ($i + 2) . ": " . $e->getMessage();
                }
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $stats;
    }

    private function parseCsv(string $filePath): array
    {
        $result = [];
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle, 0, ";");
        if (!$headers) return [];

        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF"); // BOM fix

        while (($row = fgetcsv($handle, 0, ";")) !== false) {
            if (count($row) === count($headers)) {
                $result[] = array_combine($headers, $row);
            }
        }
        fclose($handle);
        return $result;
    }

    private function getOrCreateCountry(string $name): int
    {
        $name = trim($name);
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    private function getOrCreateGames(int $year, string $type, string $city, int $countryId): int
    {
        $type = strtoupper($type);
        $stmt = $this->pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type LIMIT 1");
        $stmt->execute([':year' => $year, ':type' => $type]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->pdo->prepare("INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country_id)");
        $stmt->execute([':year' => $year, ':type' => $type, ':city' => $city, ':country_id' => $countryId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function getOrCreateAthlete($name, $surname, $birthDate, $birthPlace, $birthCountryId, $deathDate, $deathPlace, $deathCountryId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM athletes WHERE first_name = :f AND last_name = :l AND birth_date <=> :b LIMIT 1");
        $stmt->execute([':f' => $name, ':l' => $surname, ':b' => $birthDate]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->pdo->prepare("INSERT INTO athletes (first_name, last_name, birth_date, birth_place, birth_country_id, death_date, death_place, death_country_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $surname, $birthDate, $birthPlace, $birthCountryId, $deathDate, $deathPlace, $deathCountryId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function getOrCreateDiscipline(string $name): int
    {
        $name = trim($name);
        $stmt = $this->pdo->prepare("SELECT id FROM disciplines WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->pdo->prepare("INSERT INTO disciplines (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertPlacement($athleteId, $gamesId, $disciplineId, $placing): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO placements (athlete_id, olympic_games_id, discipline_id, placing) VALUES (?,?,?,?)");
        $stmt->execute([$athleteId, $gamesId, $disciplineId, $placing ?: null]);
    }

    private function normalizeDate(?string $value): ?string
    {
        if (empty($value)) return null;
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}