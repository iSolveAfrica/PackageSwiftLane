/* Design-system links must resolve to an explicit route and an existing controller.
 * No application, database or authentication required. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const routes = new Map([...fs.readFileSync(path.join(root, 'helpers/sl_routes.php'), 'utf8')
  .matchAll(/'([^']+)'\s*=>\s*'([^']+\.php[^']*)'/g)].map(m => [m[1], m[2]]));
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]);
}
const errors = [];
for (const [board, url] of routes) {
  if (!fs.existsSync(path.join(root, url.split('?')[0]))) errors.push(`${board}: missing ${url}`);
}
const files = [...walk(path.join(root, 'views/sl')), ...walk(path.join(root, 'helpers'))].filter(f => f.endsWith('.php'));
for (const file of files) {
  for (const m of fs.readFileSync(file, 'utf8').matchAll(/sl_route\(\s*['"]([^'"]+)['"]/g)) {
    if (!routes.has(m[1])) errors.push(`${path.relative(root, file)}: unmapped ${m[1]} silently falls back to Overview`);
  }
}
assert.deepEqual(errors, [], errors.join('\n'));
assert.equal(routes.get('DevBilling'), 'message_costs.php');
console.log(`Design route check passed (${routes.size} routes, ${files.length} templates/helpers).`);
