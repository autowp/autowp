import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, input, output, signal} from '@angular/core';
import {form, FormField} from '@angular/forms/signals';
import {getPerspectiveTranslation} from '@utils/translations';

import {APIPerspectiveService} from '../../../api/perspective/perspective.service';

@Component({
  selector: 'app-moder-pictures-perspective-picker',
  imports: [FormField, AsyncPipe],
  templateUrl: './moder-pictures-perspective-picker.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesPerspectivePicker {
  readonly #perspectiveService = inject(APIPerspectiveService);

  protected readonly perspectives$ = this.#perspectiveService.getPerspectives$();

  readonly perspectiveID = input.required<number>();
  readonly perspectiveChanged = output<number>();

  readonly #selection = signal('0');
  protected readonly select = form(this.#selection);

  constructor() {
    effect(() => {
      this.#selection.set(`${this.perspectiveID()}`);
    });
  }

  protected change() {
    this.perspectiveChanged.emit(Number(this.#selection()));
  }

  protected getPerspectiveTranslation(id: string): string {
    return getPerspectiveTranslation(id);
  }
}
