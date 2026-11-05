import type {ElementRef} from '@angular/core';
import type {FormValueControl} from '@angular/forms/signals';

import {ChangeDetectionStrategy, Component, input, model, output, viewChild} from '@angular/core';

// Native Signal Forms select bindings currently accept a scalar string. This control keeps
// multi-selection as string[] without introducing a reactive-forms value accessor.
@Component({
  selector: 'app-select-multiple',
  templateUrl: './select-multiple.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {class: 'd-block flex-grow-1'},
})
export class SelectMultiple implements FormValueControl<string[]> {
  readonly value = model<string[]>([]);
  readonly disabled = input(false);
  readonly label = input('');
  readonly options = input<readonly {id: string; name: string}[]>([]);
  readonly touch = output();
  private readonly select = viewChild.required<ElementRef<HTMLSelectElement>>('select');

  focus(options?: FocusOptions): void {
    this.select().nativeElement.focus(options);
  }

  protected onChange(select: HTMLSelectElement): void {
    if (this.disabled()) return;
    this.value.set(Array.from(select.selectedOptions, (option) => option.value).filter((value) => value !== ''));
  }
}
