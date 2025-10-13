<?php
/**
 * Script untuk convert halaman admin lama ke template baru
 * Usage: php convert_to_new_template.php <filename.php>
 */

if ($argc < 2) {
    die("Usage: php convert_to_new_template.php <filename.php>\n");
}

$filename = $argv[1];
$filepath = __DIR__ . '/' . $filename;

if (!file_exists($filepath)) {
    die("File not found: $filepath\n");
}

echo "Converting $filename to new template...\n";

$content = file_get_contents($filepath);

// Backup original
$backup_file = str_replace('.php', '_old_backup.php', $filename);
copy($filepath, __DIR__ . '/' . $backup_file);
echo "Backup created: $backup_file\n";

// Replace head section
$old_head_pattern = '/<html lang="en">/';
$new_head = '<html lang="id" data-bs-theme="light">';
$content = preg_replace($old_head_pattern, $new_head, $content);

// Replace CSS links
$content = preg_replace(
    '/<link href="\.\.\/css\/bootstrap\.min\.css" rel="stylesheet">/',
    '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">',
    $content
);

$content = preg_replace(
    '/<link href="https:\/\/cdn\.jsdelivr\.net\/npm\/bootstrap-icons@1\.11\.0\/font\/bootstrap-icons\.css" rel="stylesheet">/',
    '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">',
    $content
);

$content = preg_replace(
    '/<link href="\.\.\/css\/admin\.css" rel="stylesheet">/',
    '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css" rel="stylesheet">',
    $content
);

// Replace navbar and sidebar
$navbar_pattern = '/<!-- Top Navbar -->.*?<\/nav>/s';
$new_navbar = '<?php include \'_header_new.php\'; ?>';
$content = preg_replace($navbar_pattern, $new_navbar, $content);

// Replace sidebar structure
$sidebar_pattern = '/<div class="container-fluid">.*?<div class="col-md-3 col-lg-2.*?<\/div>\s*<\/div>/s';
$new_sidebar = '<?php include \'_sidebar_new.php\'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">';
$content = preg_replace($sidebar_pattern, $new_sidebar, $content, 1);

// Replace closing tags
$content = preg_replace(
    '/<\/main>\s*<\/div>\s*<\/div>\s*<script src="\.\.\/js\/bootstrap\.bundle\.min\.js"><\/script>/',
    '</div>
    </main>

    <?php include \'_scripts_new.php\'; ?>',
    $content
);

// Save converted file
file_put_contents($filepath, $content);
echo "Conversion completed: $filename\n";
echo "Backup saved as: $backup_file\n";
?>
