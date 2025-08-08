import {NgStyle} from '@angular/common';
import {AfterViewInit, ChangeDetectionStrategy, Component, computed, HostListener, input, signal} from '@angular/core';
import {PictureItem} from '@grpc/spec.pb';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-gallery-carousel-item-area',
  imports: [NgStyle, NgbTooltip],
  templateUrl: './area.component.html',
  styleUrl: './area.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AreaComponent implements AfterViewInit {
  readonly styles = input.required<Record<string, number> | undefined>({});
  readonly area = input.required<PictureItem>();

  readonly #windowHeight = signal<number>(0);

  protected readonly placement = computed<'bottom' | 'top'>(() => {
    const winHeight = this.#windowHeight();
    const styles = this.styles();
    const nodeOffset = styles?.['top.px'] || 0;
    const nodeHeight = styles?.['height.px'] || 0;
    const winCenter = winHeight == undefined ? 0 : winHeight / 2;
    const nodeCenter = nodeOffset == undefined || nodeHeight == undefined ? 0 : nodeOffset + nodeHeight / 2;

    return winCenter > nodeCenter ? 'bottom' : 'top';
  });

  ngAfterViewInit(): void {
    this.onResize();
  }

  @HostListener('window:resize')
  protected onResize() {
    this.#windowHeight.set(window.innerHeight);
  }
}
