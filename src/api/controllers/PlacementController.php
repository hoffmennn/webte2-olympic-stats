<?php

require_once __DIR__ . "/../../config.php";

class PlacementController
{
    private PDO $pdo;

    private array $allowedSortColumns = [
        'surname' => 'a.last_name',
        'year' => 'og.year',
        'discipline' => 'd.name',
    ];

    public function __construct() {
        $this->pdo = connectDatabase();
    }


    //GET
    public function getAthletePlacements($id)
    {
        $athlete = $this->findById((int)$id);
        if (!$athlete) {
            Response::json(['error' => 'Athlete not found'], 404);
        }

        $stmt = $this->pdo->prepare( "SELECT
                r.id AS placement_id,
                r.placing,
                r.olympic_games_id,
                r.discipline_id,
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
            ORDER BY og.year DESC");

        $stmt->execute([':id' => $id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['results' => $results], 200);
    }

    //GET all placements
    public function index()
    {
        $filters    = $this->resolveFilters();
        $sort       = $this->resolveSort();
        $pagination = $this->resolvePagination();

        $total = $this->countRows($filters);
        $rows  = $this->fetchRows($filters, $sort, $pagination);

        Response::json([
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'pagination' => $this->buildPagination($pagination, $total),
            'dropdowns'  => $this->getDropdownOptions(),
        ]);
    }

    // POST
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            return Response::json(["error" => "Invalid JSON"], 400);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO placements (athlete_id, olympic_games_id, discipline_id, placing)
            VALUES (:athlete_id, :olympic_games_id, :discipline_id, :placing)
        ");

        $stmt->execute([
            'athlete_id'       => $data['athlete_id'],
            'olympic_games_id' => $data['olympic_games_id'],
            'discipline_id'    => $data['discipline_id'],
            'placing'          => $data['placing']
        ]);

        $id = $this->pdo->lastInsertId();
        return Response::json(["id" => $id, "message" => "Created"], 201);
    }

    // DELETE
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM placements WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            return Response::json(["error" => "Not found"], 404);
        }

        return Response::json(null, 204);
    }

    public function update($id)
    {
        // 1. Načítanie a validácia JSON vstupu
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            return Response::json(["error" => "Neplatné JSON dáta"], 400);
        }

        // 2. Kontrola, či záznam s týmto ID vôbec existuje
        $stmt = $this->pdo->prepare("SELECT * FROM placements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        $placement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$placement) {
            return Response::json(["error" => "Umiestnenie s ID $id nebolo nájdené"], 404);
        }

        // 3. Príprava hodnôt (ak kľúč v JSON chýba, použijeme pôvodnú hodnotu z DB)
        // Použijeme null coalescing operátor ??
        $athleteId  = $data['athlete_id']       ?? $placement['athlete_id'];
        $gamesId    = $data['olympic_games_id'] ?? $placement['olympic_games_id'];
        $disciplineId = $data['discipline_id']  ?? $placement['discipline_id'];
        $placing    = $data['placing']          ?? $placement['placing'];

        try {
            // 4. Samotný UPDATE dotaz
            $updateStmt = $this->pdo->prepare("
                UPDATE placements 
                SET 
                    athlete_id = :athlete_id, 
                    olympic_games_id = :olympic_games_id, 
                    discipline_id = :discipline_id, 
                    placing = :placing
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':athlete_id'       => $athleteId,
                ':olympic_games_id' => $gamesId,
                ':discipline_id'    => $disciplineId,
                ':placing'          => $placing,
                ':id'               => (int)$id
            ]);

            return Response::json([
                "message" => "Záznam úspešne aktualizovaný",
                "id"      => (int)$id
            ], 200);

        } catch (PDOException $e) {
            // Ošetrenie chýb (napr. ak ID atléta alebo hry neexistuje - Foreign Key Error)
            return Response::json([
                "error" => "Chyba pri aktualizácii: " . $e->getMessage()
            ], 500);
        }
    }


    // GET method
    private function resolveFilters(): array
    {
        return [
            'year'     => filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: null,
            'discipline' => isset($_GET['discipline']) ? htmlspecialchars(trim($_GET['discipline'])) : null,
            'type'     => isset($_GET['type']) ? htmlspecialchars(trim($_GET['type'])) : null,
            'placing'    => filter_input(INPUT_GET, 'placing', FILTER_VALIDATE_INT) ?: null,
        ];
    }


    private function resolveSort(): array
    {
        $col = $_GET['sort'] ?? null;
        $dir = $_GET['dir']  ?? null;

        $validCol = isset($this->allowedSortColumns[$col]) ? $col : null;

        $validDir = $dir && in_array(strtoupper($dir), ['ASC', 'DESC'])
            ? strtoupper($dir)
            : null;

        return [
            'column' => $validCol,
            'dir'    => $validDir,
        ];
    }

    private function resolvePagination(): array
    {
        $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
        $perPage = $perPage === null ? 10 : (int)$perPage;
        $perPage = in_array($perPage, [10, 20, 50, 0]) ? $perPage : 10;

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $page = max(1, $page);

        return [
            'page'    => $page,
            'perPage' => $perPage,
            'offset'  => $perPage === 0 ? 0 : ($page - 1) * $perPage,
        ];
    }

    // SQL

    private function countRows(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = "SELECT COUNT(*) FROM placements r
                JOIN athletes      a  ON r.athlete_id       = a.id
                JOIN olympic_games og ON r.olympic_games_id = og.id
                JOIN disciplines   d  ON r.discipline_id    = d.id
                JOIN countries     c  ON og.country_id      = c.id
                $where";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function fetchRows(array $filters, array $sort, array $pagination): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $orderBy = 'ORDER BY og.year DESC';
        if ($sort['column'] && $sort['dir']) {
            $dbColumn = $this->allowedSortColumns[$sort['column']];
            $orderBy  = "ORDER BY $dbColumn {$sort['dir']}";
        }

        $limit = '';
        if ($pagination['perPage'] > 0) {
            $limit = "LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}";
        }

        $sql = "SELECT
                    r.id as placement_id,
                    r.placing,
                    a.id,
                    a.first_name,
                    a.last_name,
                    og.year,
                    og.type,
                    c.name AS country,
                    d.name AS discipline
                FROM placements r
                JOIN athletes      a  ON r.athlete_id       = a.id
                JOIN olympic_games og ON r.olympic_games_id = og.id
                JOIN disciplines   d  ON r.discipline_id    = d.id
                JOIN countries     c  ON og.country_id      = c.id
                $where
                $orderBy
                $limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if ($filters['year']) {
            $conditions[] = "og.year = :year";
            $params[':year'] = $filters['year'];
        }

        if ($filters['placing']) {
            $conditions[] = "r.placing = :placing";
            $params[':placing'] = $filters['placing'];
        }

        if ($filters['type']) {
            $conditions[] = "og.type = :type";
            $params[':type'] = $filters['type'];
        }

        if ($filters['discipline']) {
            $conditions[] = "d.name = :discipline";
            $params[':discipline'] = $filters['discipline'];
        }

        $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
        return [$where, $params];
    }

    private function getDropdownOptions(): array
    {
        $years = $this->pdo
            ->query("SELECT DISTINCT year FROM olympic_games ORDER BY year DESC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $placing = $this->pdo
            ->query("SELECT DISTINCT placing FROM placements ORDER BY placing ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $categories = $this->pdo
            ->query("SELECT DISTINCT name FROM disciplines ORDER BY name ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $year = $this->pdo
            ->query("SELECT DISTINCT type FROM olympic_games ORDER BY type ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        return [
            'years'      => $years,
            'categories' => $categories,
            'types'      => $year,
            'placing'    => $placing,
        ];
    }

    private function buildPagination(array $pagination, int $total): array
    {
        $perPage     = $pagination['perPage'];
        $currentPage = $pagination['page'];
        $totalPages  = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'current'   => $currentPage,
            'total'     => $totalPages,
            'perPage'   => $perPage,
            'totalRows' => $total,
            'hasNext'   => $currentPage < $totalPages,
            'hasPrev'   => $currentPage > 1,
        ];
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT 
        a.id
        FROM athletes a
        WHERE a.id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }


}