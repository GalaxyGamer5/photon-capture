const fs = require('fs');
const html = fs.readFileSync('gallery/gallery.html', 'utf8');
const scripts = html.match(/<script>([\s\S]*?)<\/script>/g);
if (scripts) {
    scripts.forEach((script, idx) => {
        const code = script.replace(/<script>|<\/script>/g, '');
        try {
            new Function(code);
            console.log(`Script ${idx} syntax OK`);
        } catch (e) {
            console.error(`Syntax error in script ${idx}:`, e);
        }
    });
}
