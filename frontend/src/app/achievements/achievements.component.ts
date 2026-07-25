import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {AchievementsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {ACHIEVEMENT_CODES, getAchievementDescriptionTranslation, getAchievementTranslation} from '@utils/translations';
import {catchError, map, Observable, of} from 'rxjs';

@Component({
  selector: 'app-achievements',
  imports: [AsyncPipe],
  templateUrl: './achievements.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AchievementsComponent {
  readonly #achievementsClient = inject(AchievementsClient);

  protected readonly codes = ACHIEVEMENT_CODES;

  protected readonly counts$: Observable<Record<string, string>> = this.#achievementsClient
    .getAchievementStats(new Empty())
    .pipe(
      map((response) => Object.fromEntries((response.items || []).map((item) => [item.code, item.usersCount]))),
      catchError(() => of({})),
    );

  protected getAchievementTranslation(code: string): string {
    return getAchievementTranslation(code);
  }

  protected getAchievementDescriptionTranslation(code: string): string {
    return getAchievementDescriptionTranslation(code);
  }
}
