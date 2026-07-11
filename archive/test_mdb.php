<?php
try {
    $dbPath = "D:\إعانة الطالبين على حل ألفاظ فتح المعين\e-Books\shamela_book\إعانة الطالبين على حل ألفاظ فتح المعين.bok";
    $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$dbPath;";
    $pdo = new PDO($dsn);
    $stmt = $pdo->query("SELECT Name FROM MSysObjects WHERE Type=1 AND Flags=0");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
