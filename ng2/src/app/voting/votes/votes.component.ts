import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges,
  OnInit
} from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { VotingService, APIVotingVariantVote } from '../../services/voting';
import Notify from '../../notify';

@Component({
  selector: 'app-voting-votes',
  templateUrl: './votes.component.html'
})
@Injectable()
export class VotingVotesComponent implements OnChanges, OnInit {
  @Input() votingID: number;
  @Input() variantID: number;

  public votes: APIVotingVariantVote[] = [];

  constructor(
    public activeModal: NgbActiveModal,
    private votingService: VotingService
  ) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnChanges(changes: SimpleChanges): void {
    this.load();
  }

  private load() {
    this.votes = [];

    const votingID = this.votingID ? this.votingID : 0;
    const variantID = this.variantID ? this.variantID : 0;

    if (votingID && variantID) {
      this.votingService
        .getVariantVotes(votingID, variantID, {
          fields: 'user'
        })
        .subscribe(
          response => {
            this.votes = response.items;
          },
          response => {
            Notify.response(response);
          }
        );
    }
  }
}
