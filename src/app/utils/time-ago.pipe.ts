import {ChangeDetectorRef, inject, NgZone, OnDestroy, Pipe, PipeTransform} from '@angular/core';
import {LanguageService} from '@services/language';

const is = (interval: number, cycle: number) => (Math.abs(cycle) >= interval ? Math.round(cycle / interval) : 0);

@Pipe({
  name: 'timeAgo',
  standalone: true,
  // eslint-disable-next-line @angular-eslint/no-pipe-impure
  pure: false,
})
export class TimeAgoPipe implements OnDestroy, PipeTransform {
  readonly #cdRef = inject(ChangeDetectorRef);
  readonly #ngZone = inject(NgZone);
  readonly #languageService = inject(LanguageService);

  #currentTimer: null | number = null;
  #lastTime: null | number = null;
  #lastValue: Date | null = null;
  #lastText = '';

  private format(time: Date) {
    const now = Date.now();
    const msecs = time.getTime() - now;
    const secs = is(1000, msecs);
    const mins = is(60, secs);
    const hours = is(60, mins);
    const days = is(24, hours);
    const weeks = is(7, days);
    const months = is(30, days);
    const years = is(12, months);

    let amt;
    let cycle: Intl.RelativeTimeFormatUnit | null;

    if (years > 0 || years < 0) {
      amt = years;
      cycle = 'year';
    } else if (months > 0 || months < 0) {
      amt = months;
      cycle = 'month';
    } else if (weeks > 0 || weeks < 0) {
      amt = weeks;
      cycle = 'week';
    } else if (days > 0 || days < 0) {
      amt = days;
      cycle = 'day';
    } else if (hours > 0 || hours < 0) {
      amt = hours;
      cycle = 'hour';
    } else if (mins > 0 || mins < 0) {
      amt = mins;
      cycle = 'minute';
    } else if (secs > 0 || secs < 0) {
      amt = secs;
      cycle = 'second';
    } else {
      amt = 0;
      cycle = null;
    }

    if (!cycle) {
      return $localize`now`;
    }

    const rtf = new Intl.RelativeTimeFormat(this.#languageService.language, {numeric: 'auto'});

    return rtf.format(amt, cycle);
  }

  public transform(value: Date | string): string {
    if (!(value instanceof Date)) {
      value = new Date(value);
    }

    if (this.hasChanged(value)) {
      this.#lastTime = value.getTime();
      this.#lastValue = value;
      this.removeTimer();
      this.createTimer();
      this.#lastText = this.format(value);
    } else {
      this.createTimer();
    }

    return this.#lastText;
  }

  ngOnDestroy(): void {
    this.removeTimer();
  }

  private createTimer() {
    if (this.#currentTimer) {
      return;
    }

    if (this.#lastValue) {
      const timeToUpdate = this.getSecondsUntilUpdate(this.#lastValue) * 1000;
      this.#currentTimer = this.#ngZone.runOutsideAngular(() => {
        if (typeof window !== 'undefined') {
          return window.setTimeout(() => {
            if (this.#lastValue) {
              this.#lastText = this.format(this.#lastValue);
            }

            this.#currentTimer = null;
            this.#ngZone.run(() => this.#cdRef.markForCheck());
          }, timeToUpdate);
        } else {
          return null;
        }
      });
    }
  }

  private removeTimer() {
    if (this.#currentTimer) {
      window.clearTimeout(this.#currentTimer);
      this.#currentTimer = null;
    }
  }

  private getSecondsUntilUpdate(value: Date) {
    const now = new Date();
    const howOld = (now.getTime() - value.getTime()) / 1000 / 60;
    if (howOld < 1) {
      return 1;
    } else if (howOld < 60) {
      return 30;
    } else if (howOld < 180) {
      return 300;
    } else {
      return 3600;
    }
  }

  private hasChanged(value: Date): boolean {
    return value.getTime() !== this.#lastTime;
  }
}
