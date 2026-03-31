<?php

class TableController
{
    private PDO $pdo;

    private array $allowedSortColumns = [
        'surname' => 'a.last_name',
        'year' => 'og.year',
        'category' => 'd.name',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTableData(): void
    {

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: http://localhost:5173'); // Vite dev server port
        header('Access-Control-Allow-Methods: GET');

        $filters    = $this->resolveFilters();
        $sort       = $this->resolveSort();
        $pagination = $this->resolvePagination();

        $total = $this->countRows($filters);
        $rows  = $this->fetchRows($filters, $sort, $pagination);

        // PHP list -> JSON
        echo json_encode([
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'pagination' => $this->buildPagination($pagination, $total),
            'dropdowns'  => $this->getDropdownOptions(),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    private function sendError(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'error' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GET method
    private function resolveFilters(): array
    {
        return [
            'year'     => filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: null,
            'category' => isset($_GET['category']) ? htmlspecialchars(trim($_GET['category'])) : null,
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

        if ($filters['category']) {
            $conditions[] = "d.name = :category";
            $params[':category'] = $filters['category'];
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
}