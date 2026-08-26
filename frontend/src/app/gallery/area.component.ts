import type {AfterViewInit} from '@angular/core';
import type {PictureItem} from '@grpc/spec.pb';

import {NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, input, signal} from '@angular/core';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {browserWindow} from '@utils/browser-window';

@Component({
  selector: 'app-gallery-carousel-item-area',
  imports: [NgStyle, NgbTooltip],
  templateUrl: './area.component.html',
  styleUrl: './area.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(window:resize)': 'onResize()',
  },
  preserveWhitespaces: false,
})
export class AreaComponent implements AfterViewInit {
  readonly #window = browserWindow();

  readonly styles = input.required<Record<string, number> | undefined>({});
  readonly area = input.required<PictureItem>();

  readonly #windowHeight = signal<number>(0);

  protected readonly placement = computed<'bottom' | 'top'>(() => {
    const winHeight = this.#windowHeight();
    const styles = this.styles();
    const nodeOffset = styles?.['top.px'] ?? 0;
    const nodeHeight = styles?.['height.px'] ?? 0;
    const winCenter = winHeight / 2;
    const nodeCenter = nodeOffset + nodeHeight / 2;

    return winCenter > nodeCenter ? 'bottom' : 'top';
  });

  ngAfterViewInit(): void {
    this.onResize();
  }

  protected onResize() {
    this.#windowHeight.set(this.#window?.innerHeight ?? 0);
  }
}
