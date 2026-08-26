import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {AchievementsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {ACHIEVEMENT_CODES, getAchievementDescriptionTranslation, getAchievementTranslation} from '@utils/translations';
import {catchError, map, of} from 'rxjs';

interface AchievementGroup {
  codes: string[];
  title: null | string;
}

const SERIES: {prefix: string; title: string}[] = [
  {prefix: 'picture-inspector-', title: $localize`Picture Inspector`},
  {prefix: 'picture-buster-', title: $localize`Picture Buster`},
  {prefix: 'spec-master-', title: $localize`Spec Master`},
  {prefix: 'commentator-', title: $localize`Commentator`},
];

function buildGroups(codes: string[]): AchievementGroup[] {
  const used = new Set<string>();

  const groups = SERIES.map((series) => {
    const seriesCodes = codes.filter((code) => code.startsWith(series.prefix));
    seriesCodes.forEach((code) => used.add(code));

    return {title: series.title, codes: seriesCodes};
  });

  const rest = codes.filter((code) => !used.has(code));

  return [{title: null, codes: rest}, ...groups];
}

@Component({
  selector: 'app-achievements',
  imports: [AsyncPipe],
  templateUrl: './achievements.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class AchievementsComponent {
  readonly #achievementsClient = inject(AchievementsClient);

  protected readonly groups: AchievementGroup[] = buildGroups(ACHIEVEMENT_CODES);

  protected readonly counts$: Observable<Record<string, string>> = this.#achievementsClient
    .getAchievementStats(new Empty())
    .pipe(
      map((response) => Object.fromEntries((response.items ?? []).map((item) => [item.code, item.usersCount]))),
      catchError(() => of({})),
    );

  protected getAchievementTranslation(code: string): string {
    return getAchievementTranslation(code);
  }

  protected getAchievementDescriptionTranslation(code: string): string {
    return getAchievementDescriptionTranslation(code);
  }
}
