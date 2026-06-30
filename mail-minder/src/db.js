const { DatabaseSync } = require('node:sqlite');
const path = require('path');
const fs = require('fs');

const DB_PATH = process.env.DB_PATH || path.join(__dirname, '..', 'data', 'mail-minder.db');

let db;

function initialize() {
  if (DB_PATH !== ':memory:') {
    fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
  }

  db = new DatabaseSync(DB_PATH);

  // WAL mode improves concurrent read performance for file databases
  if (DB_PATH !== ':memory:') {
    db.exec('PRAGMA journal_mode = WAL');
  }

  db.exec(`
    CREATE TABLE IF NOT EXISTS reminders (
      id           INTEGER PRIMARY KEY AUTOINCREMENT,
      event_id     TEXT    NOT NULL,
      channel      TEXT    NOT NULL CHECK(channel IN ('email','facebook','instagram','newsletter','other')),
      days_before  INTEGER NOT NULL,
      note         TEXT,
      completed_at TEXT,
      created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_reminders_event_id ON reminders(event_id);
  `);

  return db;
}

function getDb() {
  if (!db) throw new Error('Database not initialized — call initialize() first');
  return db;
}

module.exports = { initialize, getDb };
