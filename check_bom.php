<?php
$files = [
    'actions/request_action.php',
    'includes/db_helper.php',
    'includes/validation_helper.php',
    'config/database.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "$file: NOT FOUND\n";
        continue;
    }
    $content = file_get_contents($file);
    $bom = pack('H*','EFBBBF');
    if (substr($content, 0, 3) === $bom) {
        echo "$file: HAS BOM\n";
    } else {
        echo "$file: CLEAN\n";
    }
}
?>
