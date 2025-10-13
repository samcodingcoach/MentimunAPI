#!/bin/bash
# Bulk convert admin pages to new template
# This script replaces HTML wrapper while keeping business logic intact

FILES="resto.php pegawai.php konsumen.php vendor.php meja.php metode_pembayaran.php promo.php informasi.php profile.php"

for FILE in $FILES; do
    if [ ! -f "$FILE" ]; then
        echo "⏩ Skip: $FILE (not found)"
        continue
    fi
    
    echo "🔄 Converting: $FILE"
    
    # Backup original
    BACKUP="${FILE%.php}_old_backup.php"
    cp "$FILE" "$BACKUP"
    echo "   ✓ Backup created: $BACKUP"
    
    # Create temp file
    TEMP="${FILE}.tmp"
    
    # Process file
    awk '
    BEGIN { in_body = 0; content_started = 0; }
    
    # Skip old HTML head until body
    /<html/ { 
        if (!content_started) {
            print "<!doctype html>";
            print "<html lang=\"id\" data-bs-theme=\"light\">";
            print "<head>";
            print "    <meta charset=\"utf-8\">";
            print "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
            next;
        }
    }
    
    /<title>/ {
        if (!content_started) {
            print $0;
            print "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css\" rel=\"stylesheet\">";
            print "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css\" rel=\"stylesheet\">";
            print "    <link href=\"https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css\" rel=\"stylesheet\" />";
            print "    <link href=\"https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css\" rel=\"stylesheet\" />";
            print "    <link href=\"../css/newadmin.css\" rel=\"stylesheet\">";
            print "</head>";
            print "<body>";
            print "    <?php include '\''_header_new.php'\''; ?>";
            print "    ";
            print "    <?php include '\''_sidebar_new.php'\''; ?>";
            print "";
            print "    <main class=\"main-content\" id=\"mainContent\">";
            print "        <div class=\"container-fluid\">";
            content_started = 1;
            next;
        }
    }
    
    # Skip old CSS links
    /<link href="\.\.\/css\/(bootstrap|admin)/ { 
        if (!content_started) next; 
    }
    
    # Skip old navbar
    /<!-- Top Navbar -->/, /<\/nav>/ {
        if (!content_started) next;
    }
    
    # Skip old sidebar structure  
    /<div class="container-fluid">/, /<div class="col-md-3/ {
        if (!content_started) next;
    }
    
    # Skip old sidebar content
    /<div class="position-sticky/, /<!-- Main Content -->/ {
        if (!content_started) next;
    }
    
    # Print content
    {
        if (content_started) {
            # Replace old closing
            if ($0 ~ /<script src="\.\.\/js\/bootstrap/) {
                print "        </div>";
                print "    </main>";
                print "";
                print "    <?php include '\''_scripts_new.php'\''; ?>";
                next;
            }
            print $0;
        }
    }
    ' "$FILE" > "$TEMP"
    
    # Replace original
    mv "$TEMP" "$FILE"
    echo "   ✅ Converted successfully"
done

echo ""
echo "🎉 Bulk conversion completed!"
echo "📁 Backups saved with _old_backup.php extension"
