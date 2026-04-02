export const escapeHtml = (value) => value
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');

export const renderToastDescription = (value) => {
  if (!value) return '';

  let html = escapeHtml(value);
  html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="font-medium text-amber-700 underline">$1</a>');
  html = html.replace(/(^|[\s(>])((https?:\/\/|www\.)[^\s<]+)/g, (match, prefix, url) => {
    if (prefix.includes('href=')) {
      return match;
    }

    const href = url.startsWith('www.') ? `https://${url}` : url;

    return `${prefix}<a href="${href}" target="_blank" rel="noopener noreferrer" class="font-medium text-amber-700 underline">${url}</a>`;
  });
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
  html = html.replace(/`([^`]+)`/g, '<code class="rounded bg-stone-100 px-1 py-0.5 text-[0.85em] text-stone-800">$1</code>');
  html = html.replace(/\n/g, '<br>');

  return html;
};

export const truncateDescription = (value, limit = 140) => {
  if (!value) return '';

  const singleLineValue = value.replace(/\s+/g, ' ').trim();
  if (singleLineValue.length <= limit) {
    return singleLineValue;
  }

  return `${singleLineValue.slice(0, limit - 1).trimEnd()}…`;
};

export const todayDateString = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

export const isLateToast = (item) => !!item?.dueOn && item.dueOn < todayDateString();

export const toDateInputValue = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

export const nextMondayFrom = (date) => {
  const next = new Date(date);
  const day = next.getDay();
  const daysUntilMonday = ((8 - day) % 7) || 7;
  next.setDate(next.getDate() + daysUntilMonday);
  return next;
};

export const defaultDueDateForPreset = (preset) => {
  const base = new Date();
  base.setHours(12, 0, 0, 0);

  switch (preset) {
    case 'tomorrow':
      base.setDate(base.getDate() + 1);
      return toDateInputValue(base);
    case 'in_2_weeks':
      base.setDate(base.getDate() + 14);
      return toDateInputValue(base);
    case 'next_monday':
      return toDateInputValue(nextMondayFrom(base));
    case 'first_monday_next_month': {
      const nextMonth = new Date(base.getFullYear(), base.getMonth() + 1, 1, 12, 0, 0, 0);
      const firstMonday = nextMondayFrom(new Date(nextMonth.getTime() - 24 * 60 * 60 * 1000));
      return toDateInputValue(firstMonday);
    }
    case 'next_week':
    default:
      base.setDate(base.getDate() + 7);
      return toDateInputValue(base);
  }
};
