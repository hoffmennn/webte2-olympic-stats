<?php

class DetailController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    public function getAthleteDetail(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Methods: GET');

        // Validacia ID - musi byt kladne cislo
        if ($id <= 0) {
            $this->sendError('Neplatné ID', 400);
        }

        $athlete = $this->fetchAthlete($id);


        if (!$athlete) {
            $this->sendError('Športovec nebol nájdený', 404);
        }

        $results = $this->fetchResults($id);

        echo json_encode([
            'athlete' => $athlete,
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    private function sendError(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // SQL
    private function fetchAthlete(int $id): ?array
    {
        $sql = "SELECT
                    a.id,
                    a.first_name,
                    a.last_name,
                    a.birth_date,
                    a.birth_place,
                    a.death_date,
                    a.death_place,
                    bc.name AS birth_country,
                    dc.name AS death_country
                FROM athletes a
                LEFT JOIN countries bc ON a.birth_country_id = bc.id
                LEFT JOIN countries dc ON a.death_country_id = dc.id
                WHERE a.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function fetchResults(int $id): array
    {
        $sql = "SELECT
                    r.placing,
                    og.year,
                    og.type,
                    og.city,
                    c.name AS oh_country,
                    d.name AS discipline
                FROM placements r
                JOIN olympic_games og ON r.olympic_games_id = og.id
                JOIN disciplines   d  ON r.discipline_id   = d.id
                JOIN countries     c  ON og.country_id     = c.id
                WHERE r.athlete_id = :id
                ORDER BY og.year DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}