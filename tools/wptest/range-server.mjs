// Tiny static server WITH HTTP Range support (php -S has none, so a browser cannot seek inside a video it serves). node tools/wptest/range-server.mjs <dir> [port]
import http from 'node:http'; import fs from 'node:fs'; import path from 'node:path';
const dir = process.argv[2], port = Number(process.argv[3] || 8100);
http.createServer((req, res) => {
	const f = path.join(dir, decodeURIComponent(new URL(req.url, 'http://x').pathname)); if (!f.startsWith(dir) || !fs.existsSync(f)) { res.writeHead(404); return res.end(); }
	const size = fs.statSync(f).size, type = f.endsWith('.webm') ? 'video/webm' : 'video/mp4', r = req.headers.range;
	if (r) { const [a, bb] = r.replace('bytes=', '').split('-'), s = Number(a), e = bb ? Number(bb) : size - 1; res.writeHead(206, { 'Content-Range': `bytes ${s}-${e}/${size}`, 'Accept-Ranges': 'bytes', 'Content-Length': e - s + 1, 'Content-Type': type, 'Access-Control-Allow-Origin': '*' }); fs.createReadStream(f, { start: s, end: e }).pipe(res); }
	else { res.writeHead(200, { 'Accept-Ranges': 'bytes', 'Content-Length': size, 'Content-Type': type, 'Access-Control-Allow-Origin': '*' }); fs.createReadStream(f).pipe(res); }
}).listen(port, '127.0.0.1', () => console.log('range server on', port));
