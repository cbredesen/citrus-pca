require('dotenv').config();
const express = require('express');
const path = require('path');
const db = require('./db');
const { getEvents, getCalendarStatus } = require('./calendar');
const reminders = require('./reminders');
const templates = require('./templates');

db.initialize();

const app = express();
app.use(express.json());
app.use(express.static(path.join(__dirname, '..', 'public')));

// ── Calendar ──────────────────────────────────────────────────────────────────

app.get('/api/events', async (req, res) => {
  try {
    res.json(await getEvents(req.query.refresh === '1'));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/calendar/status', (req, res) => {
  res.json(getCalendarStatus());
});

// ── Reminders ─────────────────────────────────────────────────────────────────

app.get('/api/reminders', async (req, res) => {
  try {
    const events = await getEvents();
    res.json(reminders.getDueReminders(events));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/events/:eventId/reminders', (req, res) => {
  try {
    res.json(reminders.getRemindersForEvent(req.params.eventId));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/reminders', (req, res) => {
  const { event_id, channel, days_before, note } = req.body;
  if (!event_id || !channel || days_before == null) {
    return res.status(400).json({ error: 'event_id, channel, and days_before are required' });
  }
  try {
    res.status(201).json(reminders.createReminder({ event_id, channel, days_before, note }));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.put('/api/reminders/:id', (req, res) => {
  const { channel, days_before, note } = req.body;
  try {
    res.json(reminders.updateReminder(Number(req.params.id), { channel, days_before, note }));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/reminders/:id/complete', (req, res) => {
  try {
    res.json(reminders.completeReminder(Number(req.params.id)));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/reminders/:id/uncomplete', (req, res) => {
  try {
    res.json(reminders.uncompleteReminder(Number(req.params.id)));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/reminders/:id', (req, res) => {
  try {
    reminders.deleteReminder(Number(req.params.id));
    res.status(204).end();
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// ── Templates ─────────────────────────────────────────────────────────────────

app.get('/api/templates', (req, res) => {
  try {
    res.json(templates.listTemplates());
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/templates/:name/render', (req, res) => {
  const { name } = req.params;
  // All query params are treated as template variables
  const vars = { ...req.query };
  try {
    const html = templates.compileTemplate(name, vars);
    res.type('html').send(html);
  } catch (err) {
    if (err.message.startsWith('Template not found') || err.message === 'Invalid template name') {
      return res.status(404).json({ error: err.message });
    }
    res.status(500).json({ error: err.message });
  }
});

// Plain-text variant for clipboard copy
app.get('/api/templates/:name/html', (req, res) => {
  const { name } = req.params;
  const vars = { ...req.query };
  try {
    const html = templates.compileTemplate(name, vars);
    res.type('text/plain').send(html);
  } catch (err) {
    if (err.message.startsWith('Template not found') || err.message === 'Invalid template name') {
      return res.status(404).json({ error: err.message });
    }
    res.status(500).json({ error: err.message });
  }
});

// ── Start ─────────────────────────────────────────────────────────────────────

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`mail-minder running at http://localhost:${PORT}`);
});
