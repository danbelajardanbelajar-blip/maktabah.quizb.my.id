<?php
try {
    $dbPath = "D:\إعانة الطالبين على حل ألفاظ فتح المعين\e-Books\shamela_book\إعانة الطالبين على حل ألفاظ فتح المعين.bok";
    // We can't use pdo_odbc if not enabled, let's just see if ADO via COM works!
    $conn = new COM("ADODB.Connection") or die("Cannot start ADO");
    $conn->Open("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=$dbPath");
    $rs = $conn->Execute("SELECT Name FROM MSysObjects WHERE Type=1 AND Flags=0");
    while (!$rs->EOF) {
        echo $rs->Fields["Name"]->Value . "\n";
        $rs->MoveNext();
    }
    $rs->Close();
    $conn->Close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
