#!/bin/bash

# fix_permissions.sh - Automatically sets proper rights for the website folder

echo "-----------------------------------------------"
echo "Setting default permissions..."
echo "- Directories: 755 (drwxr-xr-x)"
echo "- Files: 644 (-rw-r--r--)"
echo "-----------------------------------------------"

# Set default permissions (755 for directories, 644 for files)
find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +

echo "Setting specific write permissions (775) for data directories..."
# Directories that need write access for uploads or data updates
WRITEABLE_DIRS=(
    "data"
    "gallery/assets"
    "gallery/data"
)

for dir in "${WRITEABLE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Updating $dir..."
        chmod -R 775 "$dir"
    else
        echo "Warning: $dir not found, skipping."
    fi
done

# Ensure specific data files are writeable
if [ -f "gallery/data/users.js" ]; then
    chmod 664 gallery/data/users.js
fi

# Make sure this script itself stays executable
chmod +x "$0"

echo "-----------------------------------------------"
echo "Permissions updated successfully!"
echo "-----------------------------------------------"
