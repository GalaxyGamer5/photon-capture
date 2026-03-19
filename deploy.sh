#!/bin/bash

# deploy.sh - Safely pulls the latest code without overwriting live server data

echo "========================================"
echo " Safe Deploy Script for photon-capture"
echo "========================================"

# Step 1: Disable filemode tracking (prevents chmod from causing conflicts)
echo "[1/5] Disabling git filemode tracking..."
git config core.filemode false

# Step 2: Stash only the data files that the application writes to
echo "[2/5] Stashing live data files..."
git stash push -m "live-server-data" \
    data/calendar.json \
    data/inquiries.json \
    data/orders.json \
    data/testimonials.json \
    gallery/data/orders.json \
    gallery/data/favorites.json

# Step 3: Pull the latest code
echo "[3/5] Pulling latest code from origin/main..."
git pull origin main

if [ $? -ne 0 ]; then
    echo "ERROR: git pull failed. Restoring stashed data..."
    git stash pop
    exit 1
fi

# Step 4: Restore the live data files (keep server's version over what was pulled)
echo "[4/5] Restoring live data files..."
git stash pop

# If there are merge conflicts in data files, keep the server's version
if [ $? -ne 0 ]; then
    echo "Conflicts in data files - keeping server versions..."
    git checkout --theirs data/calendar.json data/inquiries.json data/orders.json data/testimonials.json gallery/data/orders.json gallery/data/favorites.json 2>/dev/null || true
    git add data/ gallery/data/ 2>/dev/null || true
    git stash drop 2>/dev/null || true
fi

# Step 5: Fix permissions
echo "[5/5] Fixing permissions..."
find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +
chmod -R 775 data gallery/assets gallery/data
if [ -f "gallery/data/users.js" ]; then
    chmod 664 gallery/data/users.js
fi
chmod +x deploy.sh fix_permissions.sh

echo "========================================"
echo " Deploy complete!"
echo "========================================"
