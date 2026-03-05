<?php


// ============================================================
// POMOCNÉ FUNKCIE - parsovanie CSV
// ============================================================

function parseCsvToAssocArray(string $filePath, string $delimiter = ";"): array
{
    $result = [];

    if (!file_exists($filePath)) {
        throw new Exception("Súbor neexistuje: $filePath");
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new Exception("Nepodarilo sa otvoriť súbor.");
    }

    $headers = fgetcsv($handle, 0, $delimiter);
    if ($headers === false || empty($headers)) {
        fclose($handle);
        throw new Exception("Hlavička CSV súboru je prázdna alebo chýba.");
    }

    // Odstranenie BOM znaku ak existuje (UTF-8 BOM)
    $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($row) === count($headers)) {
            $result[] = array_combine($headers, $row);
        }
    }

    fclose($handle);
    return $result;
}

// ============================================================
// POMOCNÉ FUNKCIE - databázové operácie
// ============================================================

function getOrCreateCountry(PDO $pdo, string $name): int
{
    $name = trim($name);
    if (empty($name)) return 0;

    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int) $pdo->lastInsertId();
}

function getOrCreateGames(PDO $pdo, int $year, string $type, string $city, int $countryId): int
{
    // Validacia ENUM hodnoty
    $validTypes = ['LOH', 'ZOH'];
    if (!in_array(strtoupper($type), $validTypes)) {
        throw new Exception("Neplatný typ OH: '$type'. Povolené hodnoty: LOH, ZOH.");
    }
    $type = strtoupper($type);

    $stmt = $pdo->prepare("SELECT id FROM olympic_games WHERE year = :year AND type = :type LIMIT 1");
    $stmt->execute([':year' => $year, ':type' => $type]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("INSERT INTO olympic_games (year, type, city, country_id) VALUES (:year, :type, :city, :country_id)");
    $stmt->execute([
            ':year'       => $year,
            ':type'       => $type,
            ':city'       => $city,
            ':country_id' => $countryId
    ]);
    return (int) $pdo->lastInsertId();
}

function getOrCreateAthlete(
        PDO $pdo,
        string $name,
        string $surname,
        ?string $birthDate,
        ?string $birthPlace,
        ?int $birthCountryId,
        ?string $deathDate,
        ?string $deathPlace,
        ?int $deathCountryId
): int {
    // Identifikacia sportovca podla priezviska a datumu narodenia
    $stmt = $pdo->prepare(
       "SELECT id FROM athletes 
            WHERE first_name = :first_name 
            AND last_name = :last_name 
            AND birth_date <=> :birth_date 
        LIMIT 1"
    );
    $stmt->execute([
            ':first_name' => $name,
            ':last_name'  => $surname,
            ':birth_date' => $birthDate
    ]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO athletes
            (first_name, last_name, birth_date, birth_place, birth_country_id,
             death_date, death_place, death_country_id)
        VALUES
            (:first_name, :last_name, :birth_date, :birth_place, :birth_country_id,
             :death_date, :death_place, :death_country_id)
    ");
    $stmt->execute([
            ':first_name'       => $name,
            ':last_name'        => $surname,
            ':birth_date'       => $birthDate,
            ':birth_place'      => $birthPlace,
            ':birth_country_id' => $birthCountryId,
            ':death_date'       => $deathDate,
            ':death_place'      => $deathPlace,
            ':death_country_id' => $deathCountryId
    ]);
    return (int) $pdo->lastInsertId();
}

function getOrCreateDiscipline(PDO $pdo, string $name): int
{
    $name = trim($name);

    $stmt = $pdo->prepare("SELECT id FROM disciplines WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("INSERT INTO disciplines (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);
    return (int) $pdo->lastInsertId();
}

function insertResult(PDO $pdo, int $athleteId, int $gamesId, int $disciplineId, ?string $placing): void
{
    // Kontrola duplicity - rovnaky sportovec, OH, disciplina
    $stmt = $pdo->prepare("
        SELECT id FROM placements
        WHERE athlete_id = :athlete_id AND olympic_games_id = :olympic_games_id AND discipline_id = :discipline_id
        LIMIT 1
    ");
    $stmt->execute([
            ':athlete_id'    => $athleteId,
            ':olympic_games_id'      => $gamesId,
            ':discipline_id' => $disciplineId
    ]);

    if ($stmt->fetchColumn()) {
        return; // Zaznam uz existuje, preskocime
    }

    $stmt = $pdo->prepare("
        INSERT INTO placements (athlete_id, olympic_games_id, discipline_id, placing)
        VALUES (:athlete_id, :olympic_games_id, :discipline_id, :placing)
    ");
    $stmt->execute([
            ':athlete_id'    => $athleteId,
            ':olympic_games_id'      => $gamesId,
            ':discipline_id' => $disciplineId,
            ':placing'       => $placing ?: null
    ]);
}

// ============================================================
// POMOCNÁ FUNKCIA - normalizacia datumu z CSV
// ============================================================

function normalizeDateOrNull(?string $value): ?string
{
    if (empty($value) || trim($value) === '') {
        return null;
    }
    // Pokus o parsovanie datumu - CSV moze mat rozne formaty
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

// ============================================================
// HLAVNÁ IMPORTOVACIA LOGIKA
// ============================================================

function importCsvToDatabase(PDO $pdo, string $filePath): array
{
    $stats = ['inserted' => 0, 'skipped' => 0, 'errors' => []];

    $rows = parseCsvToAssocArray($filePath, ";");

    if (empty($rows)) {
        $stats['errors'][] = "CSV súbor je prázdny.";
        return $stats;
    }

    // Ocakavane stlpce podla hlavicky
    $requiredColumns = [
            'placing', 'name', 'surname', 'birth_day', 'birth_place', 'birth_country',
            'death_day', 'death_place', 'death_country',
            'oh_type', 'oh_year', 'discipline', 'oh_city', 'oh_country'
    ];

    // Kontrola ci CSV obsahuje vsetky ocakavane stlpce
    $csvColumns = array_keys($rows[0]);
    $missing = array_diff($requiredColumns, $csvColumns);
    if (!empty($missing)) {
        $stats['errors'][] = "Chýbajú stĺpce: " . implode(', ', $missing);
        return $stats;
    }

    $pdo->beginTransaction();

    try {
        foreach ($rows as $i => $row) {
            $lineNum = $i + 2; // +2 pretoze riadok 1 je hlavicka

            try {
                // --- Krajiny ---
                $birthCountryId = !empty($row['birth_country'])
                        ? getOrCreateCountry($pdo, $row['birth_country'])
                        : null;

                $deathCountryId = !empty($row['death_country'])
                        ? getOrCreateCountry($pdo, $row['death_country'])
                        : null;

                $ohCountryId = getOrCreateCountry($pdo, $row['oh_country']);

                // --- Olympijske hry ---
                $gamesId = getOrCreateGames(
                        $pdo,
                        (int) $row['oh_year'],
                        $row['oh_type'],
                        $row['oh_city'],
                        $ohCountryId
                );

                // --- Sportovec ---
                $athleteId = getOrCreateAthlete(
                        $pdo,
                        $row['name'],
                        $row['surname'],
                        normalizeDateOrNull($row['birth_day']),
                        $row['birth_place'] ?: null,
                        $birthCountryId,
                        normalizeDateOrNull($row['death_day']),
                        $row['death_place'] ?: null,
                        $deathCountryId
                );

                // --- Disciplina ---
                $disciplineId = getOrCreateDiscipline($pdo, $row['discipline']);

                // --- Vysledok ---
                insertResult($pdo, $athleteId, $gamesId, $disciplineId, $row['placing']);

                $stats['inserted']++;

            } catch (Exception $e) {
                $stats['skipped']++;
                $stats['errors'][] = "Riadok $lineNum: " . $e->getMessage();
            }
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $stats['errors'][] = "Kritická chyba, import zrušený: " . $e->getMessage();
    }

    return $stats;
}

// ============================================================
// SPRACOVANIE FORMULÁRA
// ============================================================

require_once('/var/www/config.php');
$importStats = null;
$parseError  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'csv') {
        $parseError = "Povolené sú iba CSV súbory.";
    } elseif ($file['error'] !== 0) {
        $parseError = "Chyba pri nahrávaní súboru (kód: {$file['error']}).";
    } else {
        try {
            $pdo = connectDatabase($hostname, $database, $username, $password);
            $importStats = importCsvToDatabase($pdo, $file['tmp_name']);
        } catch (Exception $e) {
            $parseError = "Chyba pripojenia k databáze: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Import olympionikov</title>
</head>
<body>

<h2>Import CSV súboru</h2>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <br><br>
    <button type="submit">Nahrať a importovať</button>
</form>

<?php if ($parseError): ?>
    <p style="color:red;"><strong>Chyba:</strong> <?= htmlspecialchars($parseError) ?></p>
<?php endif; ?>

<?php if ($importStats !== null): ?>
    <h3>Výsledok importu</h3>
    <p>✅ Spracovaných záznamov: <strong><?= $importStats['inserted'] ?></strong></p>
    <p>⚠️ Preskočených záznamov: <strong><?= $importStats['skipped'] ?></strong></p>

    <?php if (!empty($importStats['errors'])): ?>
        <h4>Chyby:</h4>
        <ul style="color:orange;">
            <?php foreach ($importStats['errors'] as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>