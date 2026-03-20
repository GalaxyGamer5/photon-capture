const cacheBuster = Date.now();
const imageFolder = "assets/asd-gallery";
let i = 1;
const imgSrc = `${imageFolder}/${i}.jpg?v=${cacheBuster}`;
console.log(`
<div class="selection-checkbox" style="display: none;">
    <input type="checkbox" id="select-${i}" data-image-index="${i}">
    <label for="select-${i}"></label>
</div>
<img src="${imgSrc}" loading="lazy" alt="Bild ${i}"
     onerror="tryNextExtension(this)">
<div class="gallery-overlay">
`);
