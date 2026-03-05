<?php


class TableController
{
    private PDO $pdo;

    // Povolené stĺpce pre sortovanie - bezpečnostný zoznam
    // Nikdy nedovoľ používateľovi poslať ľubovoľný názov stĺpca priamo do SQL
    private array $allowedSortColumns = [
        'surname' => 'a.last_name',
        'year' => 'og.year',
        'category' => 'd.name',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTableData(): array
    {
        // 1. Spracovanie vstupov
        $filters = $this->resolveFilters();
        $sort = $this->resolveSort();
        $pagination = $this->resolvePagination();

        // 2. Ziskanie dat
        $total = $this->countRows($filters);
        $rows = $this->fetchRows($filters, $sort, $pagination);

        // 3. Vratenie vsetkych dat pre view
        return [
            'rows' => $rows,
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $this->buildPagination($pagination, $total),
            'dropdowns' => $this->getDropdownOptions(),
        ];
    }

    // -------------------------
    // VSTUP - filtre z GET
    // -------------------------

    private function resolveFilters(): array
    {
        return [
            // filter_var pre integer, null ak nie je zadany
            'year' => filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: null,
            // htmlspecialchars pre string - ochrana pred XSS
            'category' => isset($_GET['category']) ? htmlspecialchars(trim($_GET['category'])) : null,
        ];
    }

    // -------------------------
    // VSTUP - sortovanie z GET
    // -------------------------

    private function resolveSort(): array
    {
        $col = $_GET['sort'] ?? null;
        $dir = $_GET['dir'] ?? null;

        // Overenie ci stlpec je v bezpecnostnom zozname
        // Ak nie je, pouzijeme default - ziadne sortovanie
        $validCol = isset($this->allowedSortColumns[$col])
            ? $col
            : null;

        // Smer moze byt len ASC alebo DESC
        $validDir = $dir && in_array(strtoupper($dir), ['ASC', 'DESC'])
            ? strtoupper($dir)
            : null;

        // Tretie kliknutie = reset (ked mame col ale dir je null)
        return [
            'column' => $validCol,
            'dir' => $validDir,
        ];
    }

    // -------------------------
    // VSTUP - strankovanie z GET
    // -------------------------

    private function resolvePagination(): array
    {
        // Explicitna kontrola null - nesmieme pouzit ?: pretoze 0 je falsy
        $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
        $perPage = $perPage === null ? 10 : (int)$perPage;
        $perPage = in_array($perPage, [10, 20, 50, 0]) ? $perPage : 10;

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $page = max(1, $page);

        return [
            'page'    => $page,
            'perPage' => $perPage,
            // Ak je perPage 0 (vsetky), offset nema zmysel - davame 0
            'offset'  => $perPage === 0 ? 0 : ($page - 1) * $perPage,
        ];
    }

    // -------------------------
    // SQL - pocet zaznamov (pre strankovanie)
    // -------------------------

    private function countRows(array $filters): int
    {
        // Rovnake WHERE podmienky ako fetchRows, bez LIMIT/OFFSET/ORDER
        [$where, $params] = $this->buildWhere($filters);

        $sql = "SELECT COUNT(*) FROM placements r
                JOIN athletes     a  ON r.athlete_id    = a.id
                JOIN olympic_games og ON r.olympic_games_id     = og.id
                JOIN disciplines  d  ON r.discipline_id = d.id
                JOIN countries    c  ON og.country_id   = c.id
                $where";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // -------------------------
    // SQL - ziskanie riadkov
    // -------------------------

    private function fetchRows(array $filters, array $sort, array $pagination): array
    {
        [$where, $params] = $this->buildWhere($filters);

        // Sortovanie - bezpecne, pretoze pouzivame allowedSortColumns whitelist
        $orderBy = 'ORDER BY og.year ASC';
        if ($sort['column'] && $sort['dir']) {
            $dbColumn = $this->allowedSortColumns[$sort['column']];
            $orderBy = "ORDER BY $dbColumn {$sort['dir']}";
        }

        // Strankovanie - perPage=0 znamena vsetky zaznamy
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
                    c.name  AS country,
                    d.name  AS discipline
                FROM placements r
                JOIN athletes      a  ON r.athlete_id    = a.id
                JOIN olympic_games og ON r.olympic_games_id      = og.id
                JOIN disciplines   d  ON r.discipline_id = d.id
                JOIN countries     c  ON og.country_id   = c.id
                $where
                $orderBy
                $limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------
    // SQL - WHERE podmienky
    // Oddelené aby sme neopakovali logiku v countRows aj fetchRows
    // -------------------------

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        if ($filters['year']) {
            $conditions[] = "og.year = :year";
            $params[':year'] = $filters['year'];
        }

        if ($filters['category']) {
            $conditions[] = "d.name = :category";
            $params[':category'] = $filters['category'];
        }

        $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
        return [$where, $params];
    }

    // -------------------------
    // Dropdown moznosti pre filtre
    // -------------------------

    private function getDropdownOptions(): array
    {
        $years = $this->pdo
            ->query("SELECT DISTINCT year FROM olympic_games ORDER BY year DESC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $categories = $this->pdo
            ->query("SELECT DISTINCT name FROM disciplines ORDER BY name ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        return [
            'years' => $years,
            'categories' => $categories,
        ];
    }

    // -------------------------
    // Strankovanie metadata pre view
    // -------------------------

    private function buildPagination(array $pagination, int $total): array
    {
        $perPage = $pagination['perPage'];
        $currentPage = $pagination['page'];
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'current' => $currentPage,
            'total' => $totalPages,
            'perPage' => $perPage,
            'totalRows' => $total,
            // View pouzije toto na vygenerovanie odkazov stranok
            'hasNext' => $currentPage < $totalPages,
            'hasPrev' => $currentPage > 1,
        ];
    }
}