import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';

const markdown = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: true,
});

markdown.renderer.rules.link_open = (tokens, index, options, env, self) => {
  const token = tokens[index];
  const href = token.attrGet('href') ?? '';

  if (href.startsWith('http://') || href.startsWith('https://')) {
    token.attrSet('target', '_blank');
    token.attrSet('rel', 'noopener noreferrer');
  }

  token.attrJoin('class', 'font-medium text-amber-700 underline');

  return self.renderToken(tokens, index, options);
};

markdown.renderer.rules.code_inline = (tokens, index) => `<code class="rounded bg-stone-100 px-1 py-0.5 text-[0.85em] text-stone-800">${markdown.utils.escapeHtml(tokens[index].content)}</code>`;

export const renderToastDescription = (value) => {
  if (!value) return '';

  return DOMPurify.sanitize(markdown.render(value));
};

export const renderSessionSummary = (value) => {
  if (!value) return '';

  const linkifiedValue = value
    .replace(/#\{(\d+)\}/g, '[#{$1}](/app/toasts/$1)')
    .replace(/(^|[^\w/])#(\d+)\b/g, (_, prefix, toastId) => `${prefix}[#${toastId}](/app/toasts/${toastId})`);

  return renderToastDescription(linkifiedValue)
    .replace(/<a /g, '<a target="_blank" rel="noopener noreferrer" ');
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

export const nextSnoozeDueOn = (dueOn) => {
  const today = new Date();
  today.setHours(12, 0, 0, 0);

  let base = new Date(today);

  if (typeof dueOn === 'string' && dueOn.trim() !== '') {
    const parsedDueAt = new Date(`${dueOn}T12:00:00`);
    if (!Number.isNaN(parsedDueAt.getTime()) && parsedDueAt > today) {
      base = parsedDueAt;
    }
  }

  base.setDate(base.getDate() + 1);

  return toDateInputValue(base);
};
