#!/bin/bash

# Script to convert menu.php to new UI template
cd /var/www/html/_resto007/public/admin

# Backup original file
cp menu.php menu_old_ui_backup.php

# Replace HTML head section
sed -i 's|<html lang="en">|<html lang="id" data-bs-theme="light">|g' menu.php
sed -i 's|../css/bootstrap.min.css|https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css|g' menu.php
sed -i 's|https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css|https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css|g' menu.php
sed -i 's|../css/admin.css|https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />\n    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />\n    <link href="../css/newadmin.css|g' menu.php

# Replace body start - from <body> to <!-- Main content -->
# This is complex, so we'll use a multi-line sed or create a new file

echo "Basic replacements done. Please manually replace:"
echo "1. Body section from <body> to <!-- Main content --> with includes"
echo "2. Closing </div></div> with </main>"
echo "3. Script section with _scripts_new.php include"
echo ""
echo "Template:"
echo "  <body>"
echo "    <?php include '_header_new.php'; ?>"
echo "    <?php include '_sidebar_new.php'; ?>"
echo "    <main class=\"main-content\" id=\"mainContent\">"
echo "      <div class=\"container-fluid\">"
echo "        ... main content ..."
echo "      </div>"
echo "    </main>"
echo "    <?php include '_scripts_new.php'; ?>"
echo "  </body>"
