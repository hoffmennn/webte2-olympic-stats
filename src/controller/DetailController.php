<?php
// controllers/DetailController.php

class DetailController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAthleteDetail(int $id): ?array
    {
        // Nacitame osobne udaje sportovca - jeden zaznam
        $athlete = $this->fetchAthlete($id);

        // Ak sportovec s danym ID neexistuje, vratime null
        // detail.php sa postara o presmerovanie alebo chybovu hlasku
        if (!$athlete) {
            return null;
        }

        // Nacitame vsetky jeho vysledky na OH - moze ich byt viac
        $results = $this->fetchResults($id);

        // Vratime oddelene osobne udaje a vysledky
        // View ich spracuje samostatne
        return [
            'athlete' => $athlete,
            'results' => $results,
        ];
    }

    // -------------------------
    // Osobne udaje sportovca
    // Pouzijeme LEFT JOIN pre krajiny - sportovec nemusi mat
    // vyplnenu krajinu narodenia ani umrtia
    // -------------------------
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
                -- LEFT JOIN pretoze krajina moze byt NULL
                LEFT JOIN countries bc ON a.birth_country_id = bc.id
                LEFT JOIN countries dc ON a.death_country_id = dc.id
                WHERE a.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        // fetch() vracia jeden riadok alebo false ak nenajde
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // -------------------------
    // Vsetky vysledky sportovca na OH
    // Jeden sportovec moze mat viac vysledkov
    // (rozne OH, rozne discipliny)
    // -------------------------
    private function fetchResults(int $id): array
    {
        $sql = "SELECT
                    r.placing,
                    og.year,
                    og.type,
                    og.city,
                    c.name  AS oh_country,
                    d.name  AS discipline
                FROM placements r
                JOIN olympic_games og ON r.olympic_games_id = og.id
                JOIN disciplines   d  ON r.discipline_id   = d.id
                JOIN countries     c  ON og.country_id     = c.id
                WHERE r.athlete_id = :id
                -- Zoradime chronologicky od najnovsich
                ORDER BY og.year DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}