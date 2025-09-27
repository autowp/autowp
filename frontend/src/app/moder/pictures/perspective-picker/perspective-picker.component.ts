import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, input, output} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {getPerspectiveTranslation} from '@utils/translations';

import {APIPerspectiveService} from '../../../api/perspective/perspective.service';

@Component({
  selector: 'app-moder-pictures-perspective-picker',
  imports: [FormsModule, AsyncPipe, ReactiveFormsModule],
  templateUrl: './perspective-picker.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesPerspectivePickerComponent {
  readonly #perspectiveService = inject(APIPerspectiveService);

  protected readonly perspectives$ = this.#perspectiveService.getPerspectives$();

  readonly perspectiveID = input.required<number>();
  readonly perspectiveChanged = output<number>();

  protected readonly select = new FormControl<number>(0, {nonNullable: true});

  constructor() {
    effect(() => {
      this.select.setValue(this.perspectiveID());
    });
  }

  protected change() {
    this.perspectiveChanged.emit(this.select.value);
  }

  protected getPerspectiveTranslation(id: string): string {
    return getPerspectiveTranslation(id);
  }
}
