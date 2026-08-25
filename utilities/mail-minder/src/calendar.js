const ical = require('node-ical');

const LOOKAHEAD_DAYS = 60;
const CACHE_TTL_MS = 30 * 60 * 1000;

let cache = { events: [], feedStatus: [], fetchedAt: null };

function getIcalUrls() {
  return (process.env.ICAL_URLS || '').split(',').map(u => u.trim()).filter(Boolean);
}

async function fetchAll() {
  const urls = getIcalUrls();
  if (!urls.length) return { events: [], feedStatus: [] };

  const now = new Date();
  const cutoff = new Date(now.getTime() + LOOKAHEAD_DAYS * 86_400_000);
  const events = [];
  const feedStatus = [];

  for (const url of urls) {
    let data;
    try {
      data = await ical.async.fromURL(url);
    } catch (err) {
      console.error(`[calendar] failed to fetch ${url}: ${err.message}`);
      feedStatus.push({ url, ok: false, eventCount: 0, error: err.message });
      continue;
    }

    let count = 0;
    for (const [uid, entry] of Object.entries(data)) {
      if (entry.type !== 'VEVENT') continue;

      const start = entry.start instanceof Date ? entry.start : new Date(entry.start);
      if (isNaN(start) || start < now || start > cutoff) continue;

      events.push({
        id: uid,
        title: entry.summary || '(No title)',
        start: start.toISOString(),
        description: entry.description || '',
        url: entry.url || null,
      });
      count++;
    }
    feedStatus.push({ url, ok: true, eventCount: count, error: null });
  }

  return {
    events: events.sort((a, b) => new Date(a.start) - new Date(b.start)),
    feedStatus,
  };
}

async function getEvents(forceRefresh = false) {
  const now = Date.now();
  if (!forceRefresh && cache.fetchedAt && now - cache.fetchedAt < CACHE_TTL_MS) {
    return cache.events;
  }
  const result = await fetchAll();
  cache.events = result.events;
  cache.feedStatus = result.feedStatus;
  cache.fetchedAt = now;
  return cache.events;
}

function getCalendarStatus() {
  const urls = getIcalUrls();
  return {
    configuredUrls: urls,
    feedStatus: cache.feedStatus,
    fetchedAt: cache.fetchedAt,
  };
}

module.exports = { getEvents, getCalendarStatus };
