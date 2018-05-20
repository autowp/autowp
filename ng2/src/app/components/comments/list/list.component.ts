import * as $ from 'jquery';
import { ACLService } from '../../../services/acl.service';
import {
  Component,
  Injectable,
  Input,
  EventEmitter,
  Output
} from '@angular/core';
import Notify from '../../../notify';
import { HttpClient } from '@angular/common/http';
import { CommentService, APIComment } from '../../../services/comment';
import { APIMessage } from '../../../services/message';
import { APIUser } from '../../../services/user';
import { AuthService } from '../../../services/auth.service';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { CommentsVotesComponent } from '../votes/votes.component';

export interface APICommentInList extends APIComment {
  showReply: boolean;
}

@Component({
  selector: 'app-comments-list',
  templateUrl: './list.component.html'
})
@Injectable()
export class CommentsListComponent {
  public canRemoveComments = false;
  public canMoveMessage = false;

  @Input() itemID: number;
  @Input() typeID: number;
  @Input() messages: APICommentInList[];
  @Input() deep: number;
  @Output() sent = new EventEmitter<string>();

  public isModer: boolean;

  constructor(
    private acl: ACLService,
    private http: HttpClient,
    private commentService: CommentService,
    public auth: AuthService,
    private modalService: NgbModal
  ) {
    this.acl
      .isAllowed('comment', 'remove')
      .then(
        allowed => (this.canRemoveComments = allowed),
        () => (this.canRemoveComments = false)
      );

    this.acl
      .isAllowed('forums', 'moderate')
      .then(
        allowed => (this.canMoveMessage = allowed),
        () => (this.canMoveMessage = false)
      );
  }

  public vote(message: APIComment, value: number) {
    this.commentService.vote(message.id, value).subscribe(
      () => {
        message.user_vote = value;

        this.commentService
          .getComment(message.id, { fields: 'vote' })
          .subscribe(
            response => (message.vote = response.vote),
            response => Notify.response(response)
          );

        // ga('send', 'event', 'comment-vote', value > 0 ? 'like' : 'dislike');
      },
      response => {
        if (response.status === 400) {
          Object.entries(response.error.invalid_params).forEach(
            ([paramKey, param]) =>
              Object.entries(param).forEach(([messageKey, iMessage]) =>
                Notify.custom(
                  {
                    icon: 'fa fa-exclamation-triangle',
                    message: iMessage
                  },
                  {
                    type: 'warning'
                  }
                )
              )
          );
        } else {
          Notify.response(response);
        }
      }
    );

    return false;
  }

  public setIsDeleted(message: APIComment, value: boolean) {
    this.commentService
      .setIsDeleted(message.id, value)
      .subscribe(
        () => (message.deleted = value),
        response => Notify.response(response)
      );
  }

  public reply(message: APICommentInList, resolve: boolean) {
    if (message.showReply) {
      message.showReply = false;
    } else {
      message.showReply = true;
    }
  }

  public showVotes(message: APIComment) {
    const modalRef = this.modalService.open(CommentsVotesComponent, {
      size: 'lg',
      centered: true
    });

    modalRef.componentInstance.messageID = message.id;
    return false;
  }

  public onSent(location: string) {
    this.sent.emit(location);
  }
}
