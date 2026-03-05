<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/controller/TableController.php';

$pdo        = connectDatabase($hostname, $database, $username, $password);
$controller = new TableController($pdo);
$data       = $controller->getTableData();

$rows       = $data['rows'];
$filters    = $data['filters'];
$sort       = $data['sort'];
$pagination = $data['pagination'];
$dropdowns  = $data['dropdowns'];

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Olympionici</title>
</head>
<body>

<!-- FILTRE -->
<form method="GET">
    <select name="year">
        <option value="">-- Rok --</option>
        <?php foreach ($dropdowns['years'] as $year): ?>
            <option value="<?= $year ?>" <?= $filters['year'] == $year ? 'selected' : '' ?>>
                <?= $year ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="category">
        <option value="">-- Kategória --</option>
        <?php foreach ($dropdowns['categories'] as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrovať</button>
    <a href="table.php">Zrušiť filter</a>
</form>

<!-- TABULKA -->
<table border="1">
    <thead>
    <tr>
        <th>Umiestnenie</th>

        <?php
        // Helper funkcia pre sort link
        // Logika: default -> ASC -> DESC -> default (reset)
        function sortLink(string $col, string $label, array $sort): string {
            $newDir = 'ASC';
            $arrow  = '';

            if ($sort['column'] === $col) {
                if ($sort['dir'] === 'ASC') {
                    $newDir = 'DESC';
                    $arrow  = ' ↑';
                } elseif ($sort['dir'] === 'DESC') {
                    // Tretie kliknutie = reset
                    return "<th><a href='table.php'>$label</a></th>";
                }
                $arrow = $sort['dir'] === 'ASC' ? ' ↑' : ' ↓';
            }

            $url = "table.php?sort=$col&dir=$newDir";
            return "<th><a href='$url'>$label$arrow</a></th>";
        }

        function buildUrl(array $override): string
        {
            // Vezmi vsetky aktualne GET parametre
            $params = $_GET;
            // Prepiseme len tie ktore chceme zmenit
            $params = array_merge($params, $override);
            return '?' . http_build_query($params);
        }
        ?>

        <?= sortLink('surname',  'Meno a priezvisko', $sort) ?>
        <?= sortLink('year',     'Rok',               $sort) ?>
        <th>Krajina</th>
        <?= sortLink('category', 'Šport',             $sort) ?>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr>
            <td colspan="5">Žiadne záznamy</td>
        </tr>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placing']) ?></td>
                <td> <a href="detail.php?id=<?=$row['id']?>">  <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> </a> </td>
                <td><?= htmlspecialchars($row['year']) ?></td>
                <td><?= htmlspecialchars($row['country']) ?></td>
                <td><?= htmlspecialchars($row['discipline']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<!-- STRANKOVANIE -->
<div>
    <?php if ($pagination['hasPrev']): ?>
        <a href="<?= buildUrl(['page' => $pagination['current'] - 1]) ?>">← Predošlá</a>
    <?php endif; ?>

    Strana <?= $pagination['current'] ?> z <?= $pagination['total'] ?>
    (celkom <?= $pagination['totalRows'] ?> záznamov)

    <?php if ($pagination['hasNext']): ?>
        <a href="<?= buildUrl(['page' => $pagination['current'] + 1]) ?>">Ďalšia →</a>
    <?php endif; ?>

    <span> | Zobraziť:
        <a href="<?= buildUrl(['per_page' => 10,  'page' => 1]) ?>">10</a>
        <a href="<?= buildUrl(['per_page' => 20,  'page' => 1]) ?>">20</a>
        <a href="<?= buildUrl(['per_page' => 0,   'page' => 1]) ?>">Všetky</a>
    </span>
</div>

</body>
</html>