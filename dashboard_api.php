<?php

// Incluir el archivo de configuración de la base de datos
require_once 'db.php';

// Asegurarse de que la respuesta sea JSON
header('Content-Type: application/json');


// Definir la zona horaria de Santiago
$santiago_timezone = new DateTimeZone('America/Santiago');
$now = new DateTime('now', $santiago_timezone);

// Las consultas deben usar el formato de la fecha de Santiago
$santiago_current_month = $now->format('m');
$santiago_current_day = $now->format('d');
$santiago_current_date = $now->format('Y-m-d');
// Definir un array para almacenar todos los resultados
$results = [];

// --- CONSULTA 1: Estadísticas de Personas (Total, Vivas, Fallecidas) ---
$sql_stats = "
    SELECT
        COUNT(id) AS total_personas,
        SUM(CASE WHEN dod IS NULL OR dod = '0000-00-00' THEN 1 ELSE 0 END) AS personas_vivas,
        SUM(CASE WHEN dod IS NOT NULL AND dod != '0000-00-00' THEN 1 ELSE 0 END) AS personas_fallecidas
    FROM
        people;
";
$stmt = $conn->prepare($sql_stats);
$stmt->execute();
$results['stats'] = $stmt->get_result()->fetch_assoc();
$stmt->close();


// --- CONSULTA 2: Cumpleaños de este Mes (Vivos y Fallecidos) ---
// Usaremos las variables de fecha de Santiago
$sql_birthdays_alive = "
    SELECT
        name,
        dob
    FROM
        people
    WHERE
        MONTH(dob) = ?
        AND (dod IS NULL OR dod = '0000-00-00')
    ORDER BY
        DAY(dob) ASC;
";
$stmt = $conn->prepare($sql_birthdays_alive);
$stmt->bind_param("i", $santiago_current_month);
$stmt->execute();
$results['birthdays_alive'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cumpleaños fallecidos
$sql_birthdays_deceased = "
    SELECT
        name,
        dob,
        dod
    FROM
        people
    WHERE
        MONTH(dob) = ?
        AND (dod IS NOT NULL AND dod != '0000-00-00')
    ORDER BY
        DAY(dob) ASC;
";
$stmt = $conn->prepare($sql_birthdays_deceased);
$stmt->bind_param("i", $santiago_current_month);
$stmt->execute();
$results['birthdays_deceased'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- CONSULTA 3: Nacimientos por Mes ---
$sql_birth_by_month = "
    SELECT
        MONTH(dob) AS mes,
        COUNT(id) AS total
    FROM
        people
    WHERE
        dob IS NOT NULL AND dob != '0000-00-00'
    GROUP BY
        mes
    ORDER BY
        mes ASC;
";
$stmt = $conn->prepare($sql_birth_by_month);
$stmt->execute();
$results['births'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- CONSULTA 4: Defunciones por Mes ---
$sql_death_by_month = "
    SELECT
        MONTH(dod) AS mes,
        COUNT(id) AS total
    FROM
        people
    WHERE
        dod IS NOT NULL AND dod != '0000-00-00'
    GROUP BY
        mes
    ORDER BY
        mes ASC;
";
$stmt = $conn->prepare($sql_death_by_month);
$stmt->execute();
$results['deaths'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- CONSULTA 5: Distribución por Género ---
$sql_gender_dist = "
    SELECT
        gender,
        COUNT(id) AS total
    FROM
        people
    GROUP BY
        gender
    ORDER BY
        gender;
";
$stmt = $conn->prepare($sql_gender_dist);
$stmt->execute();
$results['gender_dist'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- CONSULTA 6: Distribución por Rango de Edad (Vivos) ---
$sql_age_ranges_alive = "
    SELECT
        CONCAT(FLOOR(DATEDIFF(CURDATE(), dob) / 365.25) DIV 10 * 10, '-', FLOOR(DATEDIFF(CURDATE(), dob) / 365.25) DIV 10 * 10 + 9) AS rango_edad,
        COUNT(id) AS total_personas
    FROM
        people
    WHERE
        dob IS NOT NULL AND dob != '0000-00-00'
        AND (dod IS NULL OR dod = '0000-00-00')
    GROUP BY
        rango_edad
    ORDER BY
        MIN(FLOOR(DATEDIFF(CURDATE(), dob) / 365.25)) ASC;
";
$stmt = $conn->prepare($sql_age_ranges_alive);
$stmt->execute();
$results['age_ranges_alive'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- CONSULTA 7: Nacimientos y Muertes por Década ---
$sql_births_deaths_by_decade = "
    SELECT
        decada,
        SUM(total_nacimientos) AS total_nacimientos,
        SUM(total_muertes) AS total_muertes
    FROM
    (
        SELECT
            FLOOR(YEAR(dob) / 10) * 10 AS decada,
            COUNT(id) AS total_nacimientos,
            0 AS total_muertes
        FROM
            people
        WHERE
            dob IS NOT NULL AND dob != '0000-00-00'
        GROUP BY
            decada

        UNION ALL

        SELECT
            FLOOR(YEAR(dod) / 10) * 10 AS decada,
            0 AS total_nacimientos,
            COUNT(id) AS total_muertes
        FROM
            people
        WHERE
            dod IS NOT NULL AND dod != '0000-00-00'
        GROUP BY
            decada
    ) AS eventos_por_decada
GROUP BY
    decada
ORDER BY
    decada ASC;
";
$stmt = $conn->prepare($sql_births_deaths_by_decade);
$stmt->execute();
$results['births_deaths_by_decade'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Devolver todos los resultados como un único objeto JSON
echo json_encode($results);

$conn->close();
