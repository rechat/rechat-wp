#!/usr/bin/env node
/**
 * Build the plugin user guide from docs/user-guide.md.
 *
 * Outputs (commit both):
 *   docs/user-guide-body.html  Fragment shown in WP Admin → Rechat → User Guide.
 *   docs/user-guide.html       Standalone page users download / print to PDF.
 *
 * Run after editing docs/user-guide.md:  npm run build:guide
 * Uses markdown-it (installed with @wordpress/scripts).
 */
const fs = require('fs');
const path = require('path');

let MarkdownIt;
try {
	MarkdownIt = require('markdown-it');
} catch (e) {
	console.error('markdown-it not found. Run "npm install" first.');
	process.exit(1);
}

const root = path.resolve(__dirname, '..');
const mdPath = path.join(__dirname, 'user-guide.md');
const indexPhp = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const versionMatch = indexPhp.match(/^\s*Version:\s*([0-9.]+)/m);
const version = versionMatch ? versionMatch[1] : '';

const md = new MarkdownIt({ html: false, linkify: false, typographer: false });

// Give every h2/h3 a stable id so the table of contents can link to it.
const usedIds = {};
function slugify(text) {
	let slug = text
		.toLowerCase()
		.replace(/[^a-z0-9\s-]/g, '')
		.trim()
		.replace(/\s+/g, '-');
	slug = 'rch-guide-' + (slug || 'section');
	if (usedIds[slug]) {
		usedIds[slug] += 1;
		slug += '-' + usedIds[slug];
	} else {
		usedIds[slug] = 1;
	}
	return slug;
}

const toc = [];
md.core.ruler.push('rch_heading_ids', (state) => {
	const tokens = state.tokens;
	for (let i = 0; i < tokens.length; i++) {
		const t = tokens[i];
		if (t.type !== 'heading_open' || (t.tag !== 'h2' && t.tag !== 'h3')) {
			continue;
		}
		const text = tokens[i + 1].content;
		const id = slugify(text);
		t.attrSet('id', id);
		toc.push({ level: t.tag === 'h2' ? 2 : 3, text, id });
	}
});

// Wrap tables so wide ones scroll instead of breaking the layout.
md.renderer.rules.table_open = () => '<div class="rch-guide__table"><table>\n';
md.renderer.rules.table_close = () => '</table></div>\n';

const source = fs.readFileSync(mdPath, 'utf8');
// The H1 is rendered by the page shell, not the body.
const bodyMd = source.replace(/^# .*\n+/, '');
const bodyHtml = md.render(bodyMd);

function escapeHtml(s) {
	return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const tocHtml =
	'<nav class="rch-guide__toc" aria-label="Contents"><h2>Contents</h2><ol>' +
	toc
		.filter((h) => h.level === 2)
		.map((h) => `<li><a href="#${h.id}">${escapeHtml(h.text)}</a></li>`)
		.join('') +
	'</ol></nav>\n';

const fragment = tocHtml + '<div class="rch-guide__content">\n' + bodyHtml + '</div>\n';
fs.writeFileSync(path.join(__dirname, 'user-guide-body.html'), fragment);

const css = fs.readFileSync(path.join(root, 'assets/css/rch-user-guide.css'), 'utf8');
const versionLine = version ? `<p class="rch-guide__meta">Rechat plugin version ${escapeHtml(version)}</p>` : '';

const standalone = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rechat Plugin User Guide</title>
<style>
body { margin: 0; background: #f6f7f7; }
.rch-guide { max-width: 960px; margin: 0 auto; padding: 32px 16px 64px; }
${css}
</style>
</head>
<body>
<main class="rch-guide">
<h1 class="rch-guide__title">Rechat Plugin User Guide</h1>
${versionLine}
${fragment}</main>
<script>
if (window.location.hash === '#print') { window.addEventListener('load', function () { window.print(); }); }
</script>
</body>
</html>
`;
fs.writeFileSync(path.join(__dirname, 'user-guide.html'), standalone);

console.log(`User guide built (v${version}): ${toc.length} headings.`);
