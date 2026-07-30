import {AsyncPipe, DecimalPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {Picture, PictureItem, PictureStatus, UpdatePictureItemRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {UserService} from '@services/user';
import {getPerspectiveTranslation} from '@utils/translations';
import {APIPerspectiveService} from 'app/api/perspective/perspective.service';
import {ToastsService} from 'app/toasts/toasts.service';
import {UserComponent} from 'app/user/user/user.component';
import {EMPTY} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

interface ThumbnailAPIPicture extends Picture {
  selected?: boolean;
}

@Component({
  selector: 'app-thumbnail',
  imports: [RouterLink, UserComponent, FormsModule, AsyncPipe, DecimalPipe],
  templateUrl: './thumbnail.component.html',
  styleUrl: './thumbnail.component.scss',
  // eslint-disable-next-line @angular-eslint/prefer-on-push-component-change-detection
  changeDetection: ChangeDetectionStrategy.Eager,
})
export class ThumbnailComponent {
  readonly #perspectiveService = inject(APIPerspectiveService);
  readonly #auth = inject(AuthService);
  readonly #userService = inject(UserService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  readonly picture = input.required<ThumbnailAPIPicture>();

  readonly route = input.required<string[]>();

  readonly selectable = input(false);
  readonly selected = output<boolean>();

  readonly showSimilar = input(true);

  protected readonly perspectiveOptions$ = this.#perspectiveService.getPerspectives$().pipe(
    map((options) =>
      options.map((option) => ({
        id: option.id,
        name: getPerspectiveTranslation(option.name),
      })),
    ),
  );
  protected readonly isModer = toSignal(this.#auth.hasRole$(Role.MODER));

  protected readonly ownerResource = rxResource({
    params: () => this.picture().ownerId,
    stream: ({params: ownerId}) => this.#userService.getUser$(ownerId),
  });

  protected savePerspective(pictureItem: PictureItem) {
    this.#picturesClient
      .updatePictureItem(
        new UpdatePictureItemRequest({
          pictureItem: new PictureItem({
            itemId: pictureItem.itemId,
            perspectiveId: pictureItem.perspectiveId,
            pictureId: pictureItem.pictureId,
            type: pictureItem.type,
          }),
          updateMask: new FieldMask({paths: ['perspective_id']}),
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe();
  }

  protected onPictureSelect(picture: ThumbnailAPIPicture) {
    if (picture) {
      picture.selected = !picture.selected;
      this.selected.emit(picture.selected);
    }
  }

  protected readonly PictureStatus = PictureStatus;
}
