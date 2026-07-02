<?php

$dir = new RecursiveDirectoryIterator(__DIR__);
$iterator = new RecursiveIteratorIterator($dir);
$regexes = [
    '/@can\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
    '/->can\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
    '/middleware\s*\(\s*[\'"]permission:([^\'"]+)[\'"]\s*\)/',
    '/->hasPermissionTo\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
    '/\[\'middleware\'\s*=>\s*\[.*?[\'"]permission:([^\'"]+)[\'"].*?\]\]/',
];

$foundPermissions = [];

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    
    // Skip vendor, storage, node_modules, .git
    if (strpos($path, '/vendor/') !== false || strpos($path, '\\vendor\\') !== false) continue;
    if (strpos($path, '/storage/') !== false || strpos($path, '\\storage\\') !== false) continue;
    if (strpos($path, '/node_modules/') !== false || strpos($path, '\\node_modules\\') !== false) continue;
    if (strpos($path, '/.git/') !== false || strpos($path, '\\.git\\') !== false) continue;
    
    if (substr($path, -4) !== '.php') continue;
    
    $content = file_get_contents($path);
    foreach ($regexes as $regex) {
        if (preg_match_all($regex, $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Handle multiple permissions in middleware like 'permission:perm1|perm2'
                $perms = explode('|', $match);
                foreach ($perms as $p) {
                    $foundPermissions[$p][] = str_replace(__DIR__, '', $path);
                }
            }
        }
    }
}

// Now get all known permissions from database/seeders
$knownPermissions = [];
$seederDir = __DIR__ . '/database/seeders';
$seederIterator = new DirectoryIterator($seederDir);
foreach ($seederIterator as $file) {
    if ($file->isDir()) continue;
    if (substr($file->getFilename(), -4) !== '.php') continue;
    
    $content = file_get_contents($file->getPathname());
    
    // Match ['name' => 'perm-name']
    if (preg_match_all('/[\'"]name[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $m) {
            $knownPermissions[$m] = true;
        }
    }
    
    // Match Permission::firstOrCreate(['name' => 'perm-name'])
    if (preg_match_all('/firstOrCreate\(\s*\[\s*[\'"]name[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $m) {
            $knownPermissions[$m] = true;
        }
    }
}

$missing = [];
foreach ($foundPermissions as $perm => $files) {
    if (!isset($knownPermissions[$perm])) {
        $missing[$perm] = array_unique($files);
    }
}

echo "Found " . count($missing) . " permissions used in code but missing from seeders:\n\n";
foreach ($missing as $perm => $files) {
    echo "- {$perm}\n";
    foreach ($files as $file) {
        echo "   (in {$file})\n";
    }
}
