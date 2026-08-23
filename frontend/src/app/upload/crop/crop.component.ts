import type {Picture} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, input, output, viewChild} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Image} from '@grpc/spec.pb';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';

import type {JcropCrop} from '../../jcrop/jcrop.component';

import {cropSummary, JcropComponent} from '../../jcrop/jcrop.component';

@Component({
  selector: 'app-upload-crop',
  imports: [AsyncPipe, JcropComponent],
  templateUrl: './crop.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadCropComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly changed = output();

  readonly picture = input.required<Picture>();
  readonly picture$ = toObservable(this.picture);

  protected readonly minSize: [number, number] = [400, 300];
  protected readonly jcrop = viewChild(JcropComponent);

  protected aspect = '';
  protected resolution = '';
  private currentCrop: JcropCrop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };

  protected initialCropFor(picture: Picture): JcropCrop {
    if (picture.image?.cropWidth && picture.image.cropHeight) {
      return {
        h: picture.image.cropHeight,
        w: picture.image.cropWidth,
        x: picture.image.cropLeft,
        y: picture.image.cropTop,
      };
    }

    return {h: picture.height, w: picture.width, x: 0, y: 0};
  }

  protected onCropChange(crop: JcropCrop): void {
    this.currentCrop = crop;
    const summary = cropSummary(crop);
    this.aspect = summary.aspect;
    this.resolution = summary.resolution;
    this.#cdr.markForCheck();
  }

  protected selectAll(): void {
    this.jcrop()?.selectAll();
  }

  protected onSave(picture: Picture) {
    picture.image ??= new Image();
    picture.image.cropLeft = this.currentCrop.x;
    picture.image.cropTop = this.currentCrop.y;
    picture.image.cropWidth = this.currentCrop.w;
    picture.image.cropHeight = this.currentCrop.h;

    this.changed.emit(void 0);
    this.activeModal.close();
  }
}
