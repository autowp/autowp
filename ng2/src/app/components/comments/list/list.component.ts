import * as $ from 'jquery';
import { ACLService } from '../../../services/acl.service';
import { Component, Injectable, Input } from '@angular/core';
import Notify from '../../../notify';
import { HttpClient } from '@angular/common/http';
import { CommentService, APIComment } from '../../../services/comment';
import { APIMessage } from '../../../services/message';
import { APIUser } from '../../../services/user';

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

  @Input() itemId: number;
  @Input() typeId: number;
  @Input() messages: APICommentInList[];
  @Input() user: APIUser;
  @Input() deep: number;
  @Input() onSent: Function;

  public isModer: boolean;

  constructor(
    private acl: ACLService,
    private http: HttpClient,
    private commentService: CommentService
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
          for (const field of response.data.invalid_params) {
            for (const iMessage of field) {
              Notify.custom(
                {
                  icon: 'fa fa-exclamation-triangle',
                  message: iMessage
                },
                {
                  type: 'warning'
                }
              );
            }
          }
        } else {
          Notify.response(response);
        }
      }
    );
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
    /* const $modal = $(require('./votes.html'));

    const $body = $modal.find('.modal-body');

    $modal.modal();
    $modal.on('hidden.bs.modal', () => {
      $modal.remove();
    });

    const $btnClose = $modal.find('.btn-secondary');

    // $btnClose.button('loading');
    this.commentService.getVotes(message.id).subscribe(
      response => {
        $body.html(response);
        // $btnClose.button('reset');
      },
      response => Notify.response(response)
    );*/
  }
}
