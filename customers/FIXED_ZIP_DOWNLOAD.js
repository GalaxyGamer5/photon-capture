// Fixed ZIP download function - paste this into gallery.html to replace lines 232-290

// Download selected images as ZIP
document.getElementById('download-selected-btn').addEventListener('click', async () => {
    if (selectedImages.size === 0) return;

    const downloadBtn = document.getElementById('download-selected-btn');
    const originalText = downloadBtn.textContent;
    downloadBtn.disabled = true;
    downloadBtn.textContent = 'Erstelle ZIP-Datei...';

    try {
        const zip = new JSZip();
        const imagesToDownload = Array.from(selectedImages).sort((a, b) => a - b);

        console.log(`Creating ZIP with ${imagesToDownload.length} images`);

        for (let i = 0; i < imagesToDownload.length; i++) {
            const index = imagesToDownload[i];
            const imgSrc = `${imageFolder}/${index}.jpg`;

            downloadBtn.textContent = `Bild ${i + 1}/${imagesToDownload.length} wird hinzugefügt...`;

            try {
                // Load image using Image element (works with file://)
                const blob = await new Promise((resolve, reject) => {
                    const img = new Image();

                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);

                        canvas.toBlob((blob) => {
                            if (blob) {
                                console.log(`Image ${index}: ${blob.size} bytes`);
                                resolve(blob);
                            } else {
                                reject(new Error('Blob conversion failed'));
                            }
                        }, 'image/jpeg', 0.92);
                    };

                    img.onerror = () => reject(new Error(`Failed to load ${imgSrc}`));
                    img.src = imgSrc;
                });

                zip.file(`Bild_${String(index).padStart(3, '0')}.jpg`, blob);
            } catch (err) {
                console.error(`Error adding image ${index} to ZIP:`, err);
            }
        }

        downloadBtn.textContent = 'Erstelle Download...';
        const zipBlob = await zip.generateAsync({ type: 'blob' });

        console.log(`ZIP created: ${zipBlob.size} bytes`);

        const dateStr = new Date().toISOString().split('T')[0];
        const filename = `${user.name}_Bilder_${dateStr}.zip`;

        saveAs(zipBlob, filename);

        downloadBtn.textContent = 'Download abgeschlossen!';
        setTimeout(() => {
            downloadBtn.textContent = originalText;
            downloadBtn.disabled = false;
            exitSelectionMode();
        }, 1500);

    } catch (error) {
        console.error('ZIP error:', error);
        alert('Fehler: ' + error.message + '. Siehe Konsole für Details.');
        downloadBtn.textContent = originalText;
        downloadBtn.disabled = false;
    }
});
