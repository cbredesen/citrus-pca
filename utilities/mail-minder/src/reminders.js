const { getDb } = require('./db');

function createReminder({ event_id, channel, days_before, note }) {
  const result = getDb()
    .prepare('INSERT INTO reminders (event_id, channel, days_before, note) VALUES (?, ?, ?, ?)')
    .run(event_id, channel, Number(days_before), note || null);
  return getReminder(Number(result.lastInsertRowid));
}

function getReminder(id) {
  return getDb().prepare('SELECT * FROM reminders WHERE id = ?').get(id);
}

function getRemindersForEvent(event_id) {
  return getDb()
    .prepare('SELECT * FROM reminders WHERE event_id = ? ORDER BY days_before DESC')
    .all(event_id);
}

function updateReminder(id, { channel, days_before, note }) {
  getDb()
    .prepare('UPDATE reminders SET channel = ?, days_before = ?, note = ? WHERE id = ?')
    .run(channel, Number(days_before), note || null, id);
  return getReminder(id);
}

function completeReminder(id) {
  getDb()
    .prepare("UPDATE reminders SET completed_at = datetime('now') WHERE id = ?")
    .run(id);
  return getReminder(id);
}

function uncompleteReminder(id) {
  getDb()
    .prepare('UPDATE reminders SET completed_at = NULL WHERE id = ?')
    .run(id);
  return getReminder(id);
}

function deleteReminder(id) {
  getDb().prepare('DELETE FROM reminders WHERE id = ?').run(id);
}

// Returns { due, upcoming, past, orphaned } relative to `today`.
// `events` is the array from calendar.getEvents() — each has { id, start }.
function getDueReminders(events, today = new Date()) {
  const all = getDb().prepare('SELECT * FROM reminders ORDER BY created_at').all();
  const byEventId = new Map(events.map(e => [e.id, e]));

  const todayMs = new Date(today).setHours(0, 0, 0, 0);

  const due = [], upcoming = [], past = [], orphaned = [];

  for (const reminder of all) {
    const event = byEventId.get(reminder.event_id);
    if (!event) { orphaned.push({ reminder }); continue; }

    const eventDate = new Date(event.start);
    const dueDate = new Date(eventDate);
    dueDate.setDate(dueDate.getDate() - reminder.days_before);
    const dueDateMs = dueDate.setHours(0, 0, 0, 0);
    const diffDays = Math.round((dueDateMs - todayMs) / 86_400_000);

    const entry = { reminder, event, dueDate: new Date(dueDateMs).toISOString(), diffDays };

    if (reminder.completed_at) {
      past.push(entry);
    } else if (diffDays <= 0) {
      due.push(entry);
    } else {
      upcoming.push(entry);
    }
  }

  due.sort((a, b) => a.diffDays - b.diffDays);
  upcoming.sort((a, b) => a.diffDays - b.diffDays);
  past.sort((a, b) => new Date(b.reminder.completed_at) - new Date(a.reminder.completed_at));

  return { due, upcoming, past, orphaned };
}

module.exports = {
  createReminder,
  getReminder,
  getRemindersForEvent,
  updateReminder,
  completeReminder,
  uncompleteReminder,
  deleteReminder,
  getDueReminders,
};
