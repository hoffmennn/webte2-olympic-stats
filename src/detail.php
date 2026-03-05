<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/controller/DetailController.php';

// -------------------------
// Validacia ID z URL
// Ocakavame ?id=123
// -------------------------

// filter_input vrati null ak parameter chyba, false ak nie je integer
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Ak ID chyba alebo nie je platne cislo, presmerujeme na tabulku
if (!$id || $id <= 0) {
    header("Location: table.php");
    exit;
}

// -------------------------
// Nacitanie dat
// -------------------------
$pdo        = connectDatabase($hostname, $database, $username, $password);
$controller = new DetailController($pdo);
$data       = $controller->getAthleteDetail($id);

// Ak sportovec s danym ID neexistuje v DB
if ($data === null) {
    header("Location: table.php");
    exit;
}

// Rozbalime pre pohodlnejsi pristup vo view
$athlete = $data['athlete'];
$results = $data['results'];
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></title>
</head>
<body>

<!-- NAVIGACIA SPAT -->
<!-- buildUrl nie je dostupna tu, dame jednoduchy odkaz -->
<a href="table.php">← Späť na zoznam</a>

<!-- OSOBNE UDAJE -->
<h1><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></h1>

<table border="1">
    <tbody>
    <tr>
        <th>Dátum narodenia</th>
        <!-- Ak je hodnota null zobrazime pomlcku -->
        <td><?= $athlete['birth_date'] ? htmlspecialchars($athlete['birth_date']) : '—' ?></td>
    </tr>
    <tr>
        <th>Miesto narodenia</th>
        <td><?= $athlete['birth_place'] ? htmlspecialchars($athlete['birth_place']) : '—' ?></td>
    </tr>
    <tr>
        <th>Krajina narodenia</th>
        <td><?= $athlete['birth_country'] ? htmlspecialchars($athlete['birth_country']) : '—' ?></td>
    </tr>
    <?php if ($athlete['death_date']): ?>
        <tr>
            <th>Dátum úmrtia</th>
            <td><?= htmlspecialchars($athlete['death_date']) ?></td>
        </tr>
        <tr>
            <!-- Miesto a krajina spojene do jedneho riadku -->
            <th>Miesto úmrtia</th>
            <td>
                <?php
                // Zostavime retazec len z dostupnych hodnot
                $deathLocation = array_filter([
                        $athlete['death_place'],
                        $athlete['death_country']
                ]);
                // Spojime ciarkou, ak su obe hodnoty null zobrazime pomlcku
                echo $deathLocation
                        ? htmlspecialchars(implode(', ', $deathLocation))
                        : '—';
                ?>
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- VYSLEDKY NA OH -->
<h2>Výsledky na olympijských hrách</h2>

<?php if (empty($results)): ?>
    <p>Žiadne výsledky</p>
<?php else: ?>
    <table border="1">
        <thead>
        <tr>
            <th>Rok</th>
            <th>Typ</th>
            <th>Mesto</th>
            <th>Krajina OH</th>
            <th>Disciplína</th>
            <th>Umiestnenie</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?= htmlspecialchars($result['year']) ?></td>
                <td><?= htmlspecialchars($result['type']) ?></td>
                <td><?= htmlspecialchars($result['city']) ?></td>
                <td><?= htmlspecialchars($result['oh_country']) ?></td>
                <td><?= htmlspecialchars($result['discipline']) ?></td>
                <td><?= htmlspecialchars($result['placing']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>