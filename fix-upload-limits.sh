#!/bin/bash
# fix-upload-limits.sh
# Run this once on the server (with sudo) to raise the max_file_uploads limit.
# That setting is PHP_INI_SYSTEM — it can only be changed in the real php.ini,
# not in .user.ini. Everything else (file size, POST size, timeouts) is handled
# by admin/.user.ini which PHP-FPM picks up automatically.
#
# Usage:
#   chmod +x fix-upload-limits.sh
#   sudo ./fix-upload-limits.sh

set -e

PHP_FPM_INI="/etc/php/8.3/fpm/php.ini"

if [ ! -f "$PHP_FPM_INI" ]; then
    echo "ERROR: $PHP_FPM_INI not found. Adjust the path at the top of this script."
    exit 1
fi

echo "Patching $PHP_FPM_INI ..."

# Function to set or replace an ini value
set_ini() {
    local key="$1"
    local value="$2"
    # If the line exists (commented or not), replace it; otherwise append it
    if grep -qE "^;?\s*${key}\s*=" "$PHP_FPM_INI"; then
        sed -i "s|^;*\s*${key}\s*=.*|${key} = ${value}|" "$PHP_FPM_INI"
    else
        echo "${key} = ${value}" >> "$PHP_FPM_INI"
    fi
    echo "  set ${key} = ${value}"
}

set_ini "max_file_uploads"    "200"
set_ini "upload_max_filesize" "20M"
set_ini "post_max_size"       "4000M"
set_ini "max_execution_time"  "300"
set_ini "max_input_time"      "600"

echo ""
echo "Restarting PHP-FPM ..."
systemctl restart php8.3-fpm
echo "Done."

echo ""
echo "Verifying values in $PHP_FPM_INI:"
grep -E "^(max_file_uploads|upload_max_filesize|post_max_size|max_execution_time|max_input_time)\s*=" "$PHP_FPM_INI"

echo ""
echo "NOTE: 'php8.3 -r' reads the CLI php.ini (/etc/php/8.3/cli/php.ini) which is"
echo "      a different file — the CLI was not changed, only FPM was. To verify FPM"
echo "      is actually using these values, visit this URL in your browser after deploy:"
echo "      https://yoursite.com/admin/api/phpinfo-check.php"

