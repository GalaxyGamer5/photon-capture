#!/bin/bash

# deploy.sh - Safely pulls the latest code without overwriting live server data

echo "========================================"
echo " Safe Deploy Script for photon-capture"
echo "========================================"

# Step 1: Disable filemode tracking
echo "[1/5] Disabling git filemode tracking..."
git config core.filemode false

# Step 2: Back up live data files to a temp location
echo "[2/5] Backing up live data files..."
mkdir -p /tmp/site-data-backup/gallery
cp -f data/*.json /tmp/site-data-backup/ 2>/dev/null || true
cp -f gallery/data/*.json /tmp/site-data-backup/gallery/ 2>/dev/null || true
cp -f gallery/data/users.js /tmp/site-data-backup/gallery/ 2>/dev/null || true
echo "Backup done."

# Step 3: Hard-reset the working tree so git pull has nothing blocking it
echo "[3/5] Resetting git state..."
git reset --hard HEAD
git clean -fd --exclude=customers/ --exclude=gallery/data/*.lock 2>/dev/null || true

# Step 4: Pull latest code
echo "[4/5] Pulling latest code from origin/main..."
git pull origin main

if [ $? -ne 0 ]; then
    echo "ERROR: git pull failed even after hard reset."
    exit 1
fi

# Step 5: Restore live data (overwrite whatever was pulled for data files)
echo "[5/5] Restoring live data files..."
cp -f /tmp/site-data-backup/*.json data/ 2>/dev/null || true
cp -f /tmp/site-data-backup/gallery/*.json gallery/data/ 2>/dev/null || true
cp -f /tmp/site-data-backup/gallery/users.js gallery/data/ 2>/dev/null || true
echo "Live data restored."

# Fix permissions
chmod -R 775 data gallery/assets gallery/data
if [ -f "gallery/data/users.js" ]; then chmod 664 gallery/data/users.js; fi
chmod +x deploy.sh fix_permissions.sh

echo "========================================"
echo " Deploy complete!"
echo "========================================"
