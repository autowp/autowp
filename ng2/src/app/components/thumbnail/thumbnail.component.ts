import {
  Component,
  Injectable,
  Input,
  OnInit,
  Output,
  EventEmitter,
  OnDestroy
} from '@angular/core';
import { APIPicture } from '../../services/picture';
import { PerspectiveService } from '../../services/perspective';
import { PictureItemService } from '../../services/picture-item';
import { APIPerspective } from '../../services/api.service';
import { ACLService } from '../../services/acl.service';
import { Subscription } from 'rxjs';

interface ThumbnailAPIPicture extends APIPicture {
  selected?: boolean;
}

@Component({
  selector: 'app-thumbnail',
  templateUrl: './thumbnail.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ThumbnailComponent implements OnInit, OnDestroy {
  @Input() picture: ThumbnailAPIPicture;
  @Input() selectable = false;
  @Output() selected = new EventEmitter<boolean>();

  public perspectiveOptions: APIPerspective[] = [];
  public isModer = false;
  private sub: Subscription;
  private pserspectiveSub: Subscription;

  constructor(
    private perspectiveService: PerspectiveService,
    private pictureItemService: PictureItemService,
    private acl: ACLService
  ) {}

  ngOnInit(): void {
    this.sub = this.acl
      .inheritsRole('moder')
      .subscribe(isModer => (this.isModer = isModer));

    if (this.picture.perspective_item) {
      this.pserspectiveSub = this.perspectiveService
        .getPerspectives()
        .subscribe(perspectives => (this.perspectiveOptions = perspectives));
    }
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
    if (this.pserspectiveSub) {
      this.pserspectiveSub.unsubscribe();
    }
  }

  public savePerspective() {
    if (this.picture.perspective_item) {
      this.pictureItemService
        .setPerspective(
          this.picture.id,
          this.picture.perspective_item.item_id,
          this.picture.perspective_item.type,
          this.picture.perspective_item.perspective_id
        )
        .subscribe();
    }
  }

  public onPictureSelect($event: any) {
    this.picture.selected = !this.picture.selected;
    this.selected.emit(this.picture.selected);
  }
}
