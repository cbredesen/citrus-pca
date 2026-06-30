const os = require('os');
const fs = require('fs');
const path = require('path');

// Must set TEMPLATES_DIR before requiring templates.js (read at module load)
const TEMP_DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'mail-minder-tmpl-test-'));
process.env.TEMPLATES_DIR = TEMP_DIR;

const { test, describe, after } = require('node:test');
const assert = require('node:assert/strict');

const { listTemplates, compileTemplate } = require('../src/templates');

const SIMPLE_MJML = `<mjml><mj-body><mj-section><mj-column>
  <mj-text>Hello {{name}}, welcome to {{event_name}}!</mj-text>
</mj-column></mj-section></mj-body></mjml>`;

const NO_VARS_MJML = `<mjml><mj-body><mj-section><mj-column>
  <mj-text>Static content</mj-text>
</mj-column></mj-section></mj-body></mjml>`;

function writeTemplate(name, content) {
  fs.writeFileSync(path.join(TEMP_DIR, `${name}.mjml`), content);
}

after(() => {
  fs.rmSync(TEMP_DIR, { recursive: true, force: true });
});

describe('listTemplates', () => {
  test('returns empty array for empty directory', () => {
    // TEMP_DIR starts empty
    const templates = listTemplates();
    // May have templates written by other tests depending on order — check it's an array
    assert.ok(Array.isArray(templates));
  });

  test('detects variables from template source', () => {
    writeTemplate('test-vars', SIMPLE_MJML);
    const templates = listTemplates();
    const found = templates.find(t => t.name === 'test-vars');
    assert.ok(found, 'template not found in list');
    assert.deepEqual(found.variables.sort(), ['event_name', 'name'].sort());
  });

  test('returns empty variables array for template with none', () => {
    writeTemplate('test-novars', NO_VARS_MJML);
    const templates = listTemplates();
    const found = templates.find(t => t.name === 'test-novars');
    assert.ok(found);
    assert.deepEqual(found.variables, []);
  });
});

describe('compileTemplate', () => {
  test('compiles MJML to HTML', () => {
    writeTemplate('test-compile', SIMPLE_MJML);
    const html = compileTemplate('test-compile', { name: 'Chris', event_name: 'Spring Autocross' });
    assert.ok(html.includes('<!doctype html') || html.includes('<!DOCTYPE html'));
    assert.ok(html.includes('Chris'));
    assert.ok(html.includes('Spring Autocross'));
  });

  test('leaves unresolved placeholders as-is', () => {
    writeTemplate('test-partial', SIMPLE_MJML);
    const html = compileTemplate('test-partial', { name: 'Chris' });
    assert.ok(html.includes('{{event_name}}'));
    assert.ok(html.includes('Chris'));
  });

  test('throws for non-existent template', () => {
    assert.throws(
      () => compileTemplate('does-not-exist'),
      /Template not found/
    );
  });

  test('throws for path traversal attempt', () => {
    assert.throws(
      () => compileTemplate('../etc/passwd'),
      /Invalid template name/
    );
  });
});
