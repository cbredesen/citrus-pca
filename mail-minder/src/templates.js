const fs = require('fs');
const path = require('path');
const mjml = require('mjml');

const TEMPLATES_DIR = process.env.TEMPLATES_DIR || path.join(__dirname, '..', 'templates');
const RESOLVED_DIR = path.resolve(TEMPLATES_DIR);

function safeTemplatePath(name) {
  const resolved = path.resolve(RESOLVED_DIR, `${name}.mjml`);
  if (!resolved.startsWith(RESOLVED_DIR + path.sep) && resolved !== RESOLVED_DIR) {
    throw new Error('Invalid template name');
  }
  return resolved;
}

function extractVariables(source) {
  const matches = source.matchAll(/\{\{(\w+)\}\}/g);
  return [...new Set([...matches].map(m => m[1]))];
}

function listTemplates() {
  if (!fs.existsSync(TEMPLATES_DIR)) return [];
  return fs.readdirSync(TEMPLATES_DIR)
    .filter(f => f.endsWith('.mjml'))
    .map(f => {
      const name = f.replace(/\.mjml$/, '');
      const source = fs.readFileSync(path.join(TEMPLATES_DIR, f), 'utf8');
      return { name, variables: extractVariables(source) };
    })
    .sort((a, b) => a.name.localeCompare(b.name));
}

function compileTemplate(name, variables = {}) {
  const filePath = safeTemplatePath(name);
  if (!fs.existsSync(filePath)) throw new Error(`Template not found: ${name}`);

  let source = fs.readFileSync(filePath, 'utf8');
  for (const [key, value] of Object.entries(variables)) {
    source = source.replaceAll(`{{${key}}}`, value);
  }

  const { html, errors } = mjml(source, { validationLevel: 'soft' });
  if (errors && errors.length) {
    errors.forEach(e => console.warn(`[templates] ${name}: ${e.formattedMessage}`));
  }
  return html;
}

module.exports = { listTemplates, compileTemplate };
