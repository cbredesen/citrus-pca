// Must set DB_PATH before any require that reads it at module load time
process.env.DB_PATH = ':memory:';

const { test, describe, beforeEach } = require('node:test');
const assert = require('node:assert/strict');

const db = require('../src/db');
db.initialize();

const {
  createReminder,
  getReminder,
  getRemindersForEvent,
  updateReminder,
  completeReminder,
  uncompleteReminder,
  deleteReminder,
  getDueReminders,
} = require('../src/reminders');

function clean() {
  db.getDb().exec('DELETE FROM reminders');
}

describe('createReminder / getReminder', () => {
  beforeEach(clean);

  test('creates and retrieves a reminder', () => {
    const r = createReminder({ event_id: 'evt-1', channel: 'email', days_before: 7, note: 'Send it' });
    assert.ok(r.id);
    assert.equal(r.event_id, 'evt-1');
    assert.equal(r.channel, 'email');
    assert.equal(r.days_before, 7);
    assert.equal(r.note, 'Send it');
    assert.equal(r.completed_at, null);

    const fetched = getReminder(r.id);
    assert.equal(fetched.id, r.id);
  });

  test('note defaults to null when omitted', () => {
    const r = createReminder({ event_id: 'evt-1', channel: 'newsletter', days_before: 3 });
    assert.equal(r.note, null);
  });
});

describe('updateReminder', () => {
  beforeEach(clean);

  test('updates channel, days_before, note', () => {
    const r = createReminder({ event_id: 'evt-1', channel: 'email', days_before: 7, note: 'Old note' });
    const updated = updateReminder(r.id, { channel: 'facebook', days_before: 3, note: 'New note' });
    assert.equal(updated.channel, 'facebook');
    assert.equal(updated.days_before, 3);
    assert.equal(updated.note, 'New note');
  });
});

describe('completeReminder / uncompleteReminder', () => {
  beforeEach(clean);

  test('sets and clears completed_at', () => {
    const r = createReminder({ event_id: 'evt-1', channel: 'email', days_before: 7 });
    assert.equal(r.completed_at, null);

    const completed = completeReminder(r.id);
    assert.ok(completed.completed_at);

    const uncompleted = uncompleteReminder(r.id);
    assert.equal(uncompleted.completed_at, null);
  });
});

describe('deleteReminder', () => {
  beforeEach(clean);

  test('removes the reminder', () => {
    const r = createReminder({ event_id: 'evt-1', channel: 'email', days_before: 7 });
    deleteReminder(r.id);
    assert.equal(getReminder(r.id), undefined);
  });
});

describe('getRemindersForEvent', () => {
  beforeEach(clean);

  test('returns only reminders for the given event', () => {
    createReminder({ event_id: 'evt-a', channel: 'email', days_before: 7 });
    createReminder({ event_id: 'evt-a', channel: 'facebook', days_before: 3 });
    createReminder({ event_id: 'evt-b', channel: 'email', days_before: 14 });

    const results = getRemindersForEvent('evt-a');
    assert.equal(results.length, 2);
    assert.ok(results.every(r => r.event_id === 'evt-a'));
  });
});

describe('getDueReminders', () => {
  beforeEach(clean);

  const TODAY = new Date('2024-04-15T12:00:00Z');

  // Event 10 days from today → April 25
  const futureEvent = { id: 'evt-future', title: 'Spring Autocross', start: '2024-04-25T09:00:00Z' };
  // Event 3 days ago → April 12
  const pastEvent = { id: 'evt-past', title: 'Club Meeting', start: '2024-04-12T18:00:00Z' };

  test('reminder is upcoming when dueDate is in the future', () => {
    // due = April 25 - 7 days = April 18 → 3 days from April 15 → upcoming
    createReminder({ event_id: 'evt-future', channel: 'email', days_before: 7 });
    const { due, upcoming } = getDueReminders([futureEvent], TODAY);
    assert.equal(upcoming.length, 1);
    assert.equal(due.length, 0);
    assert.equal(upcoming[0].diffDays, 3);
  });

  test('reminder is due when dueDate is today', () => {
    // due = April 25 - 10 days = April 15 → today → due
    createReminder({ event_id: 'evt-future', channel: 'newsletter', days_before: 10 });
    const { due } = getDueReminders([futureEvent], TODAY);
    assert.equal(due.length, 1);
    assert.equal(due[0].diffDays, 0);
  });

  test('reminder is due (overdue) when dueDate is in the past', () => {
    // due = April 25 - 12 days = April 13 → 2 days ago → overdue
    createReminder({ event_id: 'evt-future', channel: 'facebook', days_before: 12 });
    const { due } = getDueReminders([futureEvent], TODAY);
    assert.equal(due.length, 1);
    assert.equal(due[0].diffDays, -2);
  });

  test('completed reminder appears in past regardless of due date', () => {
    const r = createReminder({ event_id: 'evt-future', channel: 'email', days_before: 7 });
    completeReminder(r.id);
    const { due, upcoming, past } = getDueReminders([futureEvent], TODAY);
    assert.equal(past.length, 1);
    assert.equal(due.length, 0);
    assert.equal(upcoming.length, 0);
  });

  test('orphaned reminder when event not in feed', () => {
    createReminder({ event_id: 'evt-gone', channel: 'email', days_before: 7 });
    const { orphaned } = getDueReminders([], TODAY);
    assert.equal(orphaned.length, 1);
    assert.equal(orphaned[0].reminder.event_id, 'evt-gone');
  });
});
