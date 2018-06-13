import {
  Component,
  Injectable,
  Input,
  OnInit,
  Output,
  EventEmitter
} from '@angular/core';
import { APIPicture } from '../../services/picture';
import { PerspectiveService } from '../../services/perspective';
import { PictureItemService } from '../../services/picture-item';
import { APIPerspective } from '../../services/api.service';
import { ACLService } from '../../services/acl.service';

interface ThumbnailAPIPicture extends APIPicture {
  selected?: boolean;
}

@Component({
  selector: 'app-thumbnail',
  templateUrl: './thumbnail.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ThumbnailComponent implements OnInit {
  @Input() picture: ThumbnailAPIPicture;
  @Input() selectable = false;
  @Output() selected = new EventEmitter<boolean>();

  // public onPictureSelect: ($event: any, picture: APIPicture) => void;
  public perspectiveOptions: APIPerspective[] = [];
  public isModer = false;

  constructor(
    private perspectiveService: PerspectiveService,
    private pictureItemService: PictureItemService,
    private acl: ACLService
  ) {}

  ngOnInit(): void {
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    if (this.picture.perspective_item) {
      this.perspectiveService.getPerspectives().then(perspectives => {
        this.perspectiveOptions = perspectives;
      });
    }
  }

  public savePerspective() {
    if (this.picture.perspective_item) {
      this.pictureItemService.setPerspective(
        this.picture.id,
        this.picture.perspective_item.item_id,
        this.picture.perspective_item.type,
        this.picture.perspective_item.perspective_id
      );
    }
  }

  public onPictureSelect($event: any) {
    this.picture.selected = !this.picture.selected;
    this.selected.emit(this.picture.selected);
  }
}
