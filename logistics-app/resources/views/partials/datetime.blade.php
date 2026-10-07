{{--
  A simpler date-and-time field. Every <input type="datetime-local"> on a page is turned
  into three parts: a calendar for the date, a box for the time that takes only digits and
  one separator (up to four digits: "8:30", "0830", "10.15"), and an AM / PM choice.
  The original input stays in the page (hidden) and keeps its name, value and min, so forms
  and other scripts work exactly as before; after changing its value from a script, call
  input._dtSync().
--}}
<style>
  /* Side by side when there is room, stacked when the field is narrow. */
  .dt-row { display:flex; flex-wrap:wrap; gap:8px; }
  .dt-row input[type=date] { flex:2 1 150px; min-width:0; }
  .dt-row input.dt-time { flex:1 1 84px; min-width:0; }
  .dt-row select.dt-half { flex:0 0 78px; width:auto; }
  /* Phones: the date takes the first line, the time and AM/PM share the second. */
  @media (max-width:480px) { .dt-row input[type=date] { flex-basis:100%; } }
  .dt-error { display:block; margin-top:4px; color:#d13438; font-size:.78rem; }
  [data-theme="dark"] .dt-error { color:#ff6b6f; }
</style>
<script>
  (function () {
    const pad = (n) => String(n).padStart(2, '0');
    const label12 = (h, m) => `${h % 12 || 12}:${pad(m)} ${h < 12 ? 'AM' : 'PM'}`;
    const T = {{ Js::from(['date' => __('Date'), 'time' => __('Time'), 'half' => __('AM or PM'), 'example' => __('e.g. 8:30'), 'needTime' => __('Enter the time as well.'), 'badTime' => __('Enter the time as hours and minutes, e.g. 8:30, then choose AM or PM.'), 'tooEarly' => __('That time has to be :time or later.')]) }};

    /** Only digits and one separator may be typed, and no more than four digits. */
    function clean(text) {
      let digits = 0, separated = false, out = '';
      for (const ch of text) {
        if (ch >= '0' && ch <= '9') { if (digits < 4) { out += ch; digits++; } }
        else if ((ch === ':' || ch === '.') && !separated && digits > 0) { out += ch; separated = true; }
      }
      return out;
    }

    /**
     * What was typed -> { hour, minute } on the 24-hour clock given AM or PM, or null.
     * "8" is 8:00, "830" and "8.30" are 8:30, "1015" is 10:15. An hour from 13 to 23 (or 0)
     * can only mean one half of the day, so it sets the AM / PM choice itself.
     */
    function parseTime(text, half) {
      const match = text.match(/^(\d{1,2})[:.](\d{2})$/) || text.match(/^(\d{1,2})$/) || text.match(/^(\d{1,2})(\d{2})$/);
      if (!match) return null;
      const hour = Number(match[1]);
      const minute = Number(match[2] || 0);
      if (hour > 23 || minute > 59) return null;
      if (hour === 0 || hour > 12) return { hour, minute, half: hour < 12 ? 'AM' : 'PM' };
      return { hour: (hour % 12) + (half === 'PM' ? 12 : 0), minute, half };
    }

    function enhance(input) {
      if (input._dtSync) return;
      const field = input.closest('.field') || input.parentElement;
      const name = field.querySelector('label')?.firstChild?.textContent.trim() || '';

      const date = Object.assign(document.createElement('input'), { type: 'date', id: input.id + '__date', required: input.required });
      date.setAttribute('aria-label', `${name} – ${T.date}`.trim());
      const time = Object.assign(document.createElement('input'), { type: 'text', className: 'dt-time', placeholder: T.example, autocomplete: 'off', maxLength: 5 });
      time.setAttribute('aria-label', `${name} – ${T.time}`.trim());
      time.setAttribute('inputmode', 'decimal'); // phones show the number pad with a dot
      const half = Object.assign(document.createElement('select'), { className: 'dt-half' });
      half.setAttribute('aria-label', `${name} – ${T.half}`.trim());
      half.append(new Option('AM', 'AM'), new Option('PM', 'PM'));
      const error = Object.assign(document.createElement('small'), { className: 'dt-error', hidden: true });
      error.setAttribute('aria-live', 'polite');

      const row = Object.assign(document.createElement('div'), { className: 'dt-row' });
      row.append(date, time, half);
      input.after(row, error);

      // The original keeps the value for the form; the visible parts take over the rest.
      input.type = 'hidden';
      input.required = false;
      const fieldLabel = field.querySelector(`label[for="${input.id}"]`);
      if (fieldLabel) fieldLabel.htmlFor = date.id;

      // Work out the value from what is on screen, and say what is wrong if anything is.
      function read() {
        const min = input.getAttribute('min') || '';
        const [minDate, minTime] = [min.slice(0, 10), min.slice(11, 16)];
        date.min = minDate;

        const typed = time.value;
        const parsed = typed ? parseTime(typed, half.value) : null;
        if (parsed && parsed.half !== half.value) half.value = parsed.half;
        const clock = parsed ? `${pad(parsed.hour)}:${pad(parsed.minute)}` : '';

        let problem = '';
        if (typed && !parsed) problem = T.badTime;
        else if (date.value && !typed) problem = T.needTime;
        else if (clock && date.value && date.value === minDate && clock < minTime) {
          const [h, m] = minTime.split(':').map(Number);
          problem = T.tooEarly.replace(':time', label12(h, m));
        }

        // The browser stops the form on whichever part is at fault.
        time.setCustomValidity(typed && problem ? problem : '');
        date.setCustomValidity(!typed && problem ? problem : '');

        return { value: date.value && clock && !problem ? `${date.value}T${clock}` : '', problem, parsed };
      }

      function showProblem(problem) {
        error.textContent = problem;
        error.hidden = !problem;
      }

      // Visible parts -> the real field, announced the way a typed change would be.
      function commit(show) {
        const { value, problem } = read();
        if (show || !problem) showProblem(problem);
        if (input.value === value) return;
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }

      // The real field -> visible parts (first load, or after a script changed it).
      input._dtSync = function () {
        date.value = input.value.slice(0, 10);
        const saved = input.value.slice(11, 16);
        if (saved) {
          const [h, m] = saved.split(':').map(Number);
          time.value = `${h % 12 || 12}:${pad(m)}`;
          half.value = h < 12 ? 'AM' : 'PM';
        } else {
          time.value = '';
        }
        read();
        showProblem('');
      };

      date.addEventListener('change', () => commit(Boolean(time.value)));
      // While typing: drop anything that isn't a digit or the one separator, and don't call
      // a half-finished time wrong yet.
      time.addEventListener('input', () => {
        const kept = clean(time.value);
        if (kept !== time.value) time.value = kept;
        commit(false);
      });
      // Leaving the box tidies what was typed ("0830" -> "8:30") and reports any problem.
      time.addEventListener('blur', () => {
        const { parsed } = read();
        if (parsed) time.value = `${parsed.hour % 12 || 12}:${pad(parsed.minute)}`;
        commit(Boolean(time.value) || Boolean(date.value));
      });
      half.addEventListener('change', () => commit(true));
      // Other scripts refresh the minimum when the field gets focus, and may change it any time.
      [date, time, half].forEach((part) => part.addEventListener('focus', () => input.dispatchEvent(new Event('focus'))));
      new MutationObserver(() => { const { problem } = read(); if (!error.hidden) showProblem(problem); }).observe(input, { attributes: true, attributeFilter: ['min'] });

      input._dtSync();
    }

    document.querySelectorAll('input[type="datetime-local"]').forEach(enhance);
  })();
</script>
