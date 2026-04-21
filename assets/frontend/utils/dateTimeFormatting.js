const resolveDateTimeFormatOptions = (options = {}, timezone = 'auto') => {
  if (typeof timezone === 'string' && timezone.trim() !== '' && timezone !== 'auto') {
    return {
      ...options,
      timeZone: timezone,
    };
  }

  return { ...options };
};

export const formatDateTimeForUser = (value, options = {}, timezone = 'auto', fallback = '') => {
  if (!value) {
    return fallback;
  }

  const parsedDate = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(parsedDate.getTime())) {
    return typeof value === 'string' ? value : fallback;
  }

  return new Intl.DateTimeFormat(
    undefined,
    resolveDateTimeFormatOptions(options, timezone),
  ).format(parsedDate);
};
