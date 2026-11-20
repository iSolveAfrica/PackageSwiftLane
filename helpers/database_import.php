<?php
/** Data-only SQL import for the superadmin migration page. Schema changes are never run. */

/** Extract exactly one SQL file without trusting paths or expanding an unbounded ZIP. */
function cdp_import_extract_zip($archivePath, $destination, $maxBytes = 536870912)
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZIP upload needs the PHP zip extension on the temp server.');
    }
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('The ZIP file could not be opened.');
    }
    try {
        if ($zip->numFiles > 100) {
            throw new RuntimeException('The ZIP contains too many entries. Upload one SQL export.');
        }
        $entry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                throw new RuntimeException('The ZIP contains an unreadable entry.');
            }
            $name = $stat['name'];
            if (substr($name, -1) === '/') {
                continue;
            }
            if (!preg_match('/\.sql$/i', $name) || $entry !== null) {
                throw new RuntimeException('A ZIP must contain exactly one .sql file and no other files.');
            }
            if (($stat['size'] ?? 0) > $maxBytes || ($stat['size'] ?? 0) <= 0) {
                throw new RuntimeException('The SQL file inside the ZIP must be under 512 MB.');
            }
            $entry = $name;
        }
        if ($entry === null) {
            throw new RuntimeException('No .sql file was found inside the ZIP.');
        }
        $input = $zip->getStream($entry);
        $output = fopen($destination, 'wb');
        if (!$input || !$output) {
            if ($input) fclose($input);
            if ($output) fclose($output);
            throw new RuntimeException('The SQL file inside the ZIP could not be extracted.');
        }
        $bytes = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) throw new RuntimeException('Could not read the ZIP contents.');
                if ($chunk === '' && !feof($input)) throw new RuntimeException('The ZIP stream stopped before extraction finished.');
                $bytes += strlen($chunk);
                if ($bytes > $maxBytes) throw new RuntimeException('The extracted SQL exceeds 512 MB.');
                if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) throw new RuntimeException('Could not write the extracted SQL file.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        if ($bytes === 0) throw new RuntimeException('The SQL file inside the ZIP is empty.');
    } finally {
        $zip->close();
    }
}

/** Decompress a gzip SQL export into the private temporary import file. */
function cdp_import_extract_gzip($archivePath, $destination, $maxBytes = 536870912)
{
    if (!function_exists('gzopen')) {
        throw new RuntimeException('Gzip upload needs the PHP zlib extension on the temp server.');
    }
    $input = @gzopen($archivePath, 'rb');
    $output = fopen($destination, 'wb');
    if (!$input || !$output) {
        if ($input) gzclose($input);
        if ($output) fclose($output);
        throw new RuntimeException('The .sql.gz file could not be opened.');
    }
    $bytes = 0;
    try {
        while (!gzeof($input)) {
            $chunk = gzread($input, 1024 * 1024);
            if ($chunk === false || ($chunk === '' && !gzeof($input))) {
                throw new RuntimeException('Could not decompress the .sql.gz file.');
            }
            $bytes += strlen($chunk);
            if ($bytes > $maxBytes) throw new RuntimeException('The decompressed SQL exceeds 512 MB.');
            if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                throw new RuntimeException('Could not write the decompressed SQL file.');
            }
        }
    } finally {
        gzclose($input);
        fclose($output);
    }
    if ($bytes === 0) throw new RuntimeException('The .sql.gz file is empty.');
}

function cdp_import_statements($path, $callback)
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Cannot read the uploaded SQL file.');
    }
    $statement = '';
    $quote = '';
    $escaped = false;
    try {
        while (($line = fgets($handle)) !== false) {
            $trim = ltrim($line);
            if (trim($statement) === '' && ($trim === '' || strncmp($trim, '--', 2) === 0 || $trim[0] === '#' || strncmp($trim, '/*', 2) === 0)) {
                $statement = '';
                continue;
            }
            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                if ($quote !== '') {
                    $statement .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === $quote) {
                        if ($i + 1 < $length && $line[$i + 1] === $quote) {
                            $statement .= $line[++$i];
                        } else {
                            $quote = '';
                        }
                    }
                } elseif ($char === "'" || $char === '"' || $char === '`') {
                    $quote = $char;
                    $statement .= $char;
                } elseif ($char === ';') {
                    $sql = trim($statement);
                    if ($sql !== '') {
                        $callback($sql);
                    }
                    $statement = '';
                } else {
                    $statement .= $char;
                }
            }
            if (strlen($statement) > 8 * 1024 * 1024) {
                throw new RuntimeException('A SQL statement exceeds the 8 MB safety limit. Export smaller INSERT batches.');
            }
        }
        if ($quote !== '' || trim($statement) !== '') {
            throw new RuntimeException('The SQL file ends with an incomplete statement.');
        }
    } finally {
        fclose($handle);
    }
}

function cdp_import_plan($path, PDO $pdo)
{
    $source = [];
    $sourceTypes = [];
    $inserts = [];
    cdp_import_statements($path, function ($sql) use (&$source, &$sourceTypes, &$inserts) {
        if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)? `?([a-zA-Z0-9_]+)`?\s*\(/i', $sql, $match)) {
            preg_match_all('/^\s*`([^`]+)`\s+\S+/m', $sql, $columns);
            $source[$match[1]] = $columns[1];
            preg_match_all('/^\s*`([^`]+)`\s+([a-zA-Z]+(?:\([^)]*\))?(?:\s+unsigned)?)/im', $sql, $types, PREG_SET_ORDER);
            foreach ($types as $type) {
                $sourceTypes[$match[1]][$type[1]] = strtolower($type[2]);
            }
        } elseif (preg_match('/^INSERT INTO `?([a-zA-Z0-9_]+)`?\s*(?:\(([^)]*)\))?\s*VALUES\s*\(/is', $sql, $match)) {
            $table = $match[1];
            $cols = isset($match[2]) && trim($match[2]) !== '' ? array_map(function ($col) { return trim($col, " `\t\r\n"); }, explode(',', $match[2])) : null;
            if (isset($inserts[$table]) && $inserts[$table] !== $cols) {
                throw new RuntimeException("Inconsistent INSERT columns for $table.");
            }
            $inserts[$table] = $cols;
        } elseif (preg_match('/^(?:SET|START TRANSACTION|COMMIT|CREATE TABLE|ALTER TABLE|DROP TABLE|LOCK TABLES|UNLOCK TABLES)\b/i', $sql)) {
            // SQL dumps contain schema and session commands. They are inspected only.
        } else {
            throw new RuntimeException('Unsupported SQL command in upload: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 70));
        }
    });
    if (!$inserts) {
        throw new RuntimeException('No INSERT data found. Upload a SQL export with data.');
    }
    $schema = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
    $schema->execute([CDP_DB_NAME]);
    $target = [];
    foreach ($schema->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $target[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
    }
    $issues = [];
    foreach ($inserts as $table => $explicit) {
        if (!isset($target[$table])) {
            $issues[] = "$table: table missing on temp";
            continue;
        }
        $columns = $explicit ?: (isset($source[$table]) ? $source[$table] : null);
        if (!$columns) {
            $issues[] = "$table: positional INSERT needs a CREATE TABLE definition in the upload";
            continue;
        }
        $missing = array_diff($columns, array_keys($target[$table]));
        if ($missing) {
            $issues[] = "$table: missing target columns " . implode(', ', $missing);
        }
        foreach ($columns as $name) {
            if (isset($sourceTypes[$table][$name], $target[$table][$name]) && cdp_import_normalize_type($sourceTypes[$table][$name]) !== cdp_import_normalize_type($target[$table][$name]['COLUMN_TYPE'])) {
                $issues[] = "$table.$name: source type " . $sourceTypes[$table][$name] . ' differs from temp type ' . $target[$table][$name]['COLUMN_TYPE'];
            }
        }
        foreach ($target[$table] as $name => $definition) {
            if (!in_array($name, $columns, true) && $definition['IS_NULLABLE'] === 'NO' && $definition['COLUMN_DEFAULT'] === null && strpos($definition['EXTRA'], 'auto_increment') === false) {
                $issues[] = "$table: required target column $name has no source value";
            }
        }
        if (!$explicit && $columns !== array_keys($target[$table])) {
            $issues[] = "$table: positional column order differs; export with explicit INSERT columns";
        }
    }
    return ['tables' => $inserts, 'source' => $source, 'issues' => $issues];
}

function cdp_import_normalize_type($type)
{
    $type = strtolower($type);
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
}

function cdp_import_apply($path, PDO $pdo)
{
    $plan = cdp_import_plan($path, $pdo);
    if ($plan['issues']) {
        throw new RuntimeException('Schema mismatch: ' . implode('; ', $plan['issues']));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($plan['tables'] as $table => $_) {
        $count = $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1')->fetchColumn();
        if ($count !== false) {
            throw new RuntimeException("$table already has data. Import into an empty temp database to avoid duplicate or mixed records.");
        }
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        cdp_import_statements($path, function ($sql) use ($pdo, $plan) {
            if (!preg_match('/^INSERT INTO `?([a-zA-Z0-9_]+)`?\s*(?:\(([^)]*)\))?\s*VALUES\s*\(/is', $sql, $match)) {
                return;
            }
            $table = $match[1];
            if (!isset($plan['tables'][$table])) {
                throw new RuntimeException("Unexpected table $table.");
            }
            if (empty($match[2])) {
                $sql = preg_replace('/^(INSERT INTO `?[a-zA-Z0-9_]+`?)\s+VALUES/is', '$1 (' . implode(', ', array_map(function ($col) { return '`' . $col . '`'; }, $plan['source'][$table])) . ') VALUES', $sql, 1);
            }
            $pdo->exec($sql);
        });
        // Re-enable checks before commit; CHECK TABLE and app-level validation can follow.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $e;
    }
    return count($plan['tables']);
}
