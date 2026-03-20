        let user = null;

        // Check session and load user data
        fetch('api/check-session.php')
            .then(r => {
                if (!r.ok) throw new Error('Not authenticated');
                return r.json();
            })
            .then(data => {
                if (!data.authenticated) {
                    window.location.href = 'index.html';
                    return;
                }

                user = data.user;
                document.getElementById('user-name').textContent = user.name;
                initializeGallery();
            })
            .catch(() => {
                window.location.href = 'index.html';
            });

        // Logout handler
        document.getElementById('logout-btn').addEventListener('click', () => {
            fetch('api/logout.php', { method: 'POST' })
                .then(() => {
                    window.location.href = 'index.html';
                })
                .catch(() => {
                    window.location.href = 'index.html';
                });
        });

        async function initializeGallery() {
            // Favorites State
            let favorites = new Set();
            let showFavoritesOnly = false;
            
            try {
                const favRes = await fetch('api/get-favorites.php');
                const favData = await favRes.json();
                if (favData.success && favData.favorites) {
                    favorites = new Set(favData.favorites.map(img => parseInt(img.replace('.jpg', ''))));
                }
            } catch(e) { console.error('Error loading favorites', e); }

            // Load Images
            const galleryGrid = document.getElementById('gallery-grid');
            const imageFolder = `assets/${user.folder}`;
            const imageCount = user.imageCount || 0;

            // Generate grid
            function renderGallery() {
                galleryGrid.innerHTML = '';

                if (imageCount > 0) {
                    for (let i = 1; i <= imageCount; i++) {
                        // Filter if favorites mode is on
                        if (showFavoritesOnly && !favorites.has(i)) continue;

                        const item = document.createElement('div');
                        item.className = 'gallery-item';
                        const isFavorite = favorites.has(i);

                        // Assuming jpg format for simplicity, could be enhanced to support others
                        const imgSrc = `${imageFolder}/${i}.jpg?v=${cacheBuster}`;

                        item.innerHTML = `
                        <div class="selection-checkbox" style="display: none;">
                            <input type="checkbox" id="select-${i}" data-image-index="${i}">
                            <label for="select-${i}"></label>
                        </div>
                        <img src="${imgSrc}" loading="lazy" alt="Bild ${i}"
                             onerror="tryNextExtension(this)">
                        <div class="gallery-overlay">
                            <button class="btn-icon favorite-btn ${isFavorite ? 'active' : ''}" onclick="toggleFavorite(event, ${i})" style="margin-right: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: ${isFavorite ? 'var(--accent-color)' : 'black'};"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                            </button>
                            <button class="btn-icon" onclick="openLightbox(${i})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                            </button>
                        </div>
                    `;

                        // Add click event to image itself too
                        item.querySelector('img').addEventListener('click', () => openLightbox(i));

                        galleryGrid.appendChild(item);
                    }
                } else {
                    galleryGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 4rem;">Noch keine Bilder verfügbar.</p>';
                }
            }

            renderGallery();

            // Try alternative image extensions if the default .jpg fails
            window.tryNextExtension = function(img) {
                const src = img.src;
                let newSrc = null;
                // Strip existing cache buster for matching, but keep it for appending
                const baseMatch = src.split('?')[0]; 
                const cb = src.split('?')[1] ? '?' + src.split('?')[1] : '';

                if (baseMatch.match(/\.jpg$/i)) {
                    newSrc = baseMatch.replace(/\.jpg$/i, '.png') + cb;
                } else if (baseMatch.match(/\.png$/i)) {
                    newSrc = baseMatch.replace(/\.png$/i, '.webp') + cb;
                }
                
                if (newSrc) {
                    img.src = newSrc;
                    // If this is the lightbox image, also update the download button URL
                    if (img.id === 'lightbox-img') {
                        const dlBtn = document.getElementById('lightbox-download');
                        if (dlBtn) dlBtn.href = newSrc;
                    }
                } else {
                    img.style.opacity = '0';
                }
            };
            // Favorites Logic
            window.toggleFavorite = async function (event, index) {
                event.stopPropagation(); // Prevent lightbox opening

                // Optimistic UI update
                if (favorites.has(index)) {
                    favorites.delete(index);
                } else {
                    favorites.add(index);
                }

                renderGallery(); // Re-render to update icons

                // Sync with server
                const imgSrc = `${index}.jpg`;
                try {
                    await fetch('api/toggle-favorite.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ image: imgSrc })
                    });
                } catch(e) { console.error('Error saving favorite', e); }

                // Update lightbox button if open
                const lightboxImg = document.getElementById('lightbox-img');
                if (lightbox.classList.contains('active') && lightboxImg.src.includes(`${index}.jpg`)) {
                    updateLightboxFavoriteBtn(index);
                }
            };

            function updateLightboxFavoriteBtn(index) {
                const btn = document.getElementById('lightbox-favorite-btn');
                const isFavorite = favorites.has(index);
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: ${isFavorite ? 'var(--accent-color)' : 'white'};"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`;
                btn.onclick = (e) => toggleFavorite(e, index);
            }

            // Filter Button
            const favFilterBtn = document.getElementById('favorites-filter-btn');
            favFilterBtn.addEventListener('click', () => {
                showFavoritesOnly = !showFavoritesOnly;
                favFilterBtn.classList.toggle('active');
                favFilterBtn.style.borderColor = showFavoritesOnly ? 'var(--accent-color)' : 'var(--glass-border)';
                favFilterBtn.style.color = showFavoritesOnly ? 'var(--accent-color)' : 'var(--text-primary)';
                favFilterBtn.textContent = showFavoritesOnly ? '❤️ Alle anzeigen' : '❤️ Favoriten';
                renderGallery();
            });

            // Lightbox Logic
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            const downloadBtn = document.getElementById('lightbox-download');
            let currentIndex = 1;

            window.openLightbox = (index) => {
                currentIndex = index;
                updateLightbox();
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
                updateLightboxFavoriteBtn(index);
            };

            function updateLightbox() {
                const imgSrc = `${imageFolder}/${currentIndex}.jpg?v=${cacheBuster}`;
                lightboxImg.src = imgSrc;
                lightboxImg.onerror = function() { tryNextExtension(this); };
                downloadBtn.href = imgSrc;
                updateLightboxFavoriteBtn(currentIndex);
            }

            function closeLightbox() {
                lightbox.classList.remove('active');
                document.body.style.overflow = '';
            }

            document.querySelector('.lightbox-close').addEventListener('click', closeLightbox);

            document.querySelector('.lightbox-prev').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentIndex > 1) {
                    currentIndex--;
                    updateLightbox();
                } else {
                    currentIndex = imageCount; // Loop
                    updateLightbox();
                }
            });

            document.querySelector('.lightbox-next').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentIndex < imageCount) {
                    currentIndex++;
                    updateLightbox();
                } else {
                    currentIndex = 1; // Loop
                    updateLightbox();
                }
            });

            // Close on background click
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) closeLightbox();
            });

            // Keyboard nav
            document.addEventListener('keydown', (e) => {
                if (!lightbox.classList.contains('active')) return;
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowLeft') document.querySelector('.lightbox-prev').click();
                if (e.key === 'ArrowRight') document.querySelector('.lightbox-next').click();
            });

            // Selection Mode
            let selectionMode = false;
            const selectedImages = new Set();

            document.getElementById('select-mode-btn').addEventListener('click', () => {
                selectionMode = true;
                document.getElementById('selection-toolbar').style.display = 'flex';
                document.getElementById('select-mode-btn').style.display = 'none';
                document.querySelectorAll('.selection-checkbox').forEach(cb => cb.style.display = 'flex');
                document.querySelectorAll('.gallery-item').forEach(item => item.classList.add('selection-active'));
            });

            document.getElementById('cancel-selection-btn').addEventListener('click', () => {
                exitSelectionMode();
            });

            function exitSelectionMode() {
                selectionMode = false;
                selectedImages.clear();
                document.getElementById('selection-toolbar').style.display = 'none';
                document.getElementById('select-mode-btn').style.display = 'block';
                document.querySelectorAll('.selection-checkbox').forEach(cb => {
                    cb.style.display = 'none';
                    cb.querySelector('input').checked = false;
                });
                document.querySelectorAll('.gallery-item').forEach(item => item.classList.remove('selection-active'));
                updateSelectionCount();
            }

            document.getElementById('select-all-btn').addEventListener('click', () => {
                document.querySelectorAll('.selection-checkbox input').forEach(cb => {
                    cb.checked = true;
                    selectedImages.add(parseInt(cb.dataset.imageIndex));
                });
                updateSelectionCount();
            });

            document.getElementById('deselect-all-btn').addEventListener('click', () => {
                document.querySelectorAll('.selection-checkbox input').forEach(cb => {
                    cb.checked = false;
                });
                selectedImages.clear();
                updateSelectionCount();
            });

            // Handle checkbox changes
            document.addEventListener('change', (e) => {
                if (e.target.matches('.selection-checkbox input')) {
                    const index = parseInt(e.target.dataset.imageIndex);
                    if (e.target.checked) {
                        selectedImages.add(index);
                    } else {
                        selectedImages.delete(index);
                    }
                    updateSelectionCount();
                }
            });

            function updateSelectionCount() {
                const count = selectedImages.size;
                document.getElementById('selection-count').textContent = `${count} Bild${count !== 1 ? 'er' : ''} ausgewählt`;
                document.getElementById('download-selected-btn').disabled = count === 0;
            }

            // Download selected images as ZIP
            document.getElementById('download-selected-btn').addEventListener('click', async () => {
                if (selectedImages.size === 0) return;

                // Check for local file protocol
                if (window.location.protocol === 'file:') {
                    alert('⚠️ WICHTIG:\n\nDer ZIP-Download funktioniert aus Sicherheitsgründen nicht bei lokalen Dateien (file://).\n\nBitte starte einen lokalen Server (z.B. "php -S localhost:8000") oder lade die Website auf einen Server hoch, damit dieses Feature funktioniert.');
                    return;
                }

                const downloadBtn = document.getElementById('download-selected-btn');
                const originalText = downloadBtn.textContent;
                downloadBtn.disabled = true;
                downloadBtn.textContent = 'Erstelle ZIP-Datei...';

                try {
                    // Create new ZIP file
                    const zip = new JSZip();
                    const imagesToDownload = Array.from(selectedImages).sort((a, b) => a - b);
                    let addedCount = 0;

                    // Add each image to the ZIP
                    for (let i = 0; i < imagesToDownload.length; i++) {
                        const index = imagesToDownload[i];
                        const imgSrc = `${imageFolder}/${index}.jpg?v=${cacheBuster}`;

                        downloadBtn.textContent = `Bild ${i + 1}/${imagesToDownload.length} wird hinzugefügt...`;

                        try {
                            // Attempt fallback extensions if .jpg fails
                            let response = await fetch(`${imageFolder}/${index}.jpg?v=${cacheBuster}`);
                            let ext = 'jpg';
                            
                            if (!response.ok) {
                                response = await fetch(`${imageFolder}/${index}.png?v=${cacheBuster}`);
                                ext = 'png';
                            }
                            if (!response.ok) {
                                response = await fetch(`${imageFolder}/${index}.webp?v=${cacheBuster}`);
                                ext = 'webp';
                            }

                            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                            const blob = await response.blob();

                            // Add to ZIP with correct extension
                            zip.file(`Bild_${String(index).padStart(3, '0')}.${ext}`, blob);
                            addedCount++;
                        } catch (err) {
                            console.error(`Error adding image ${index} to ZIP:`, err);
                        }
                    }

                    if (addedCount === 0) {
                        throw new Error('Keine Bilder konnten geladen werden (CORS/Netzwerkfehler).');
                    }

                    // Generate ZIP file
                    downloadBtn.textContent = 'Erstelle Download...';
                    const zipBlob = await zip.generateAsync({ type: 'blob' });

                    // Create filename with user name and date
                    const today = new Date();
                    const dateStr = today.toISOString().split('T')[0];
                    const filename = `${user.name}_Bilder_${dateStr}.zip`;

                    // Download the ZIP file
                    saveAs(zipBlob, filename);

                    downloadBtn.textContent = 'Download abgeschlossen!';
                    setTimeout(() => {
                        downloadBtn.textContent = originalText;
                        downloadBtn.disabled = false;
                        exitSelectionMode();
                    }, 1500);

                } catch (error) {
                    console.error('ZIP creation error:', error);
                    alert('Fehler beim Erstellen der ZIP-Datei.');
                    downloadBtn.textContent = originalText;
                    downloadBtn.disabled = false;
                }
            });

            // --- Password Change Logic ---
            const pwModal = document.getElementById('pw-modal');
            const changePwBtn = document.getElementById('change-pw-btn');
            const cancelPwBtn = document.getElementById('cancel-pw-btn');
            const savePwBtn = document.getElementById('save-pw-btn');

            changePwBtn.addEventListener('click', () => {
                pwModal.style.display = 'flex';
            });

            cancelPwBtn.addEventListener('click', () => {
                pwModal.style.display = 'none';
            });

            savePwBtn.addEventListener('click', async () => {
                const oldPw = document.getElementById('old-pw').value;
                const newPw = document.getElementById('new-pw').value;
                const confirmPw = document.getElementById('confirm-pw').value;

                if (!oldPw || !newPw || !confirmPw) {
                    alert("Bitte alle Felder ausfüllen.");
                    return;
                }

                if (newPw !== confirmPw) {
                    alert("Die neuen Passwörter stimmen nicht überein.");
                    return;
                }

                // Client-side hashing (SHA-1 to match existing system)
                async function sha1(message) {
                    const msgBuffer = new TextEncoder().encode(message);
                    const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                }

                savePwBtn.disabled = true;
                savePwBtn.textContent = "Speichert...";

                try {
                    const oldHash = await sha1(oldPw);
                    const newHash = await sha1(newPw);

                    const response = await fetch('api/change_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            userId: user.id,
                            oldPasswordHash: oldHash,
                            newPasswordHash: newHash
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert("Passwort erfolgreich geändert!");
                        pwModal.style.display = 'none';
                        // Clear fields
                        document.getElementById('old-pw').value = '';
                        document.getElementById('new-pw').value = '';
                        document.getElementById('confirm-pw').value = '';
                    } else {
                        alert("Fehler: " + (result.message || "Unbekannter Fehler"));
                    }
                } catch (e) {
                    console.error(e);
                    alert("Verbindungsfehler beim Speichern.");
                } finally {
                    savePwBtn.disabled = false;
                    savePwBtn.textContent = "Speichern";
                }
            });
        } // End of initializeGallery
