import type {OnDestroy, PipeTransform} from '@angular/core';

import {inject, Pipe, signal} from '@angular/core';
import {LanguageService} from '@services/language';
import {browserWindow} from '@utils/browser-window';

const is = (interval: number, cycle: number) => (Math.abs(cycle) >= interval ? Math.round(cycle / interval) : 0);

// Intl.RelativeTimeFormat construction is not free and the language rarely changes; one per
// language, shared by every pipe instance.
const formatters = new Map<string, Intl.RelativeTimeFormat>();
const formatterFor = (language: string): Intl.RelativeTimeFormat => {
  let rtf = formatters.get(language);
  if (!rtf) {
    rtf = new Intl.RelativeTimeFormat(language, {numeric: 'auto'});
    formatters.set(language, rtf);
  }

  return rtf;
};

@Pipe({
  name: 'timeAgo',
  standalone: true,
  // Impure so the text keeps up with wall-clock time; re-renders are driven by #tick (a signal
  // the host view reads through transform()), not by NgZone - so this works the same under zoneless
  // change detection.
  // eslint-disable-next-line @angular-eslint/no-pipe-impure
  pure: false,
})
export class TimeAgoPipe implements OnDestroy, PipeTransform {
  readonly #languageService = inject(LanguageService);
  readonly #window = browserWindow();

  // Bumped by the refresh timer. Reading it inside transform() subscribes the host view, so a bump
  // marks that view for check (via Angular's change-detection scheduler, zone or zoneless alike).
  readonly #tick = signal(0);

  #timer: null | ReturnType<Window['setTimeout']> = null;
  #cache: null | {language: string; text: string; tick: number; time: number} = null;

  public transform(value: Date | string): string {
    // Registers the host view as a consumer of #tick; every later bump re-runs this pipe.
    const tick = this.#tick();

    const date = value instanceof Date ? value : new Date(value);
    const time = date.getTime();
    const language = this.#languageService.language;

    if (this.#cache && this.#cache.time === time && this.#cache.tick === tick && this.#cache.language === language) {
      return this.#cache.text;
    }

    const text = this.#format(date, language);
    this.#cache = {language, text, tick, time};

    this.#scheduleRefresh(date);

    return text;
  }

  ngOnDestroy(): void {
    this.#clearTimer();
  }

  #format(time: Date, language: string): string {
    const msecs = time.getTime() - Date.now();
    const secs = is(1000, msecs);
    const mins = is(60, secs);
    const hours = is(60, mins);
    const days = is(24, hours);
    const weeks = is(7, days);
    const months = is(30, days);
    const years = is(12, months);

    let amount: number;
    let cycle: Intl.RelativeTimeFormatUnit;

    if (years !== 0) {
      amount = years;
      cycle = 'year';
    } else if (months !== 0) {
      amount = months;
      cycle = 'month';
    } else if (weeks !== 0) {
      amount = weeks;
      cycle = 'week';
    } else if (days !== 0) {
      amount = days;
      cycle = 'day';
    } else if (hours !== 0) {
      amount = hours;
      cycle = 'hour';
    } else if (mins !== 0) {
      amount = mins;
      cycle = 'minute';
    } else if (secs !== 0) {
      amount = secs;
      cycle = 'second';
    } else {
      return $localize`now`;
    }

    return formatterFor(language).format(amount, cycle);
  }

  // Browser only: on the server a pending timer would keep SSR's whenStable() from resolving, and
  // the text is a point-in-time snapshot there anyway.
  #scheduleRefresh(value: Date): void {
    if (this.#timer !== null || !this.#window) {
      return;
    }

    this.#timer = this.#window.setTimeout(
      () => {
        this.#timer = null;
        this.#tick.update((tick) => tick + 1);
      },
      this.#secondsUntilUpdate(value) * 1000,
    );
  }

  #clearTimer(): void {
    if (this.#timer !== null) {
      this.#window?.clearTimeout(this.#timer);
      this.#timer = null;
    }
  }

  #secondsUntilUpdate(value: Date): number {
    const minutesOld = (Date.now() - value.getTime()) / 1000 / 60;
    if (minutesOld < 1) {
      return 1;
    }
    if (minutesOld < 60) {
      return 30;
    }
    if (minutesOld < 180) {
      return 300;
    }

    return 3600;
  }
}
