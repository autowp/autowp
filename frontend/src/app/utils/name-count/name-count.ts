import type {TemplateRef} from '@angular/core';

import {ChangeDetectionStrategy, Component, input, output} from '@angular/core';
import {RouterLink} from '@angular/router';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-name-count',
  imports: [RouterLink, NgbPopover],
  templateUrl: './name-count.html',
  styleUrl: './name-count.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NameCount {
  readonly popoverShown = output();
  readonly popoverHidden = output();
  readonly routerLink = input.required<string | string[]>();
  readonly name = input.required<string>();
  readonly count = input.required<number>();
  readonly newCount = input(0);
  readonly popover = input<null | TemplateRef<unknown>>(null);
  readonly popoverTitle = input<null | string>(null);
}
