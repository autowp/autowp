import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkComponent} from 'ngx-remark';

// Like policy.component: the whole document is one $localize block rendered as Markdown by <remark>,
// with an explicit @@terms-of-service-body id so a bumped date or a reworded clause does not re-key
// the unit and orphan the nine translations. Keep the "Last updated" date in the first line in sync
// with real changes, and bump TERMS_VERSION in the acceptance flow when a change is significant.
const termsText = $localize`:@@terms-of-service-body:*Last updated: 29 August 2026*

These Terms of Service ("Terms") are an agreement between you and the administration of the websites **wheelsage.org** and **autowp.ru** (together, the "Site", "we", "us") about your use of the Site. The Site is a community car catalogue and encyclopaedia.

By creating an account on the Site, or by otherwise using the Site, you agree to these Terms. If you do not agree, please do not use the Site.

## Related documents

These Terms work together with:

* the [Privacy Policy](/policy), which explains what personal data we handle and why;
* the [Rules](/rules), which set out how to behave on the Site and how to edit the catalogue.

The Privacy Policy and the Rules are part of these Terms. If a specific Rule and these Terms conflict, these Terms prevail.

## Who may use the Site

You must be at least 16 years old, or the lower age of digital consent set by the law of your country, to create an account. By registering you confirm that you meet this requirement.

Each person may hold one account. You are responsible for everything done through your account and for keeping your sign-in credentials secure. Tell us promptly if you believe someone else has used your account.

## Your content

"Your content" means everything you submit to the Site: comments, forum posts, private messages, catalogue and specification edits, votes, and pictures you upload.

You keep ownership of your content. We do not claim ownership of it and we do not sell it.

### What you promise about your content

For everything you submit, you confirm that:

* you are the author, or you have all the rights and permissions needed to submit it and to grant the licences below;
* it does not infringe anyone's copyright, trademark, image rights, privacy, or other rights;
* it is not unlawful, defamatory, misleading, or malicious, and contains no malware;
* where a picture shows an identifiable person, you have their consent if the law requires it;
* the source, author, and licensing information you enter into the upload form is accurate.

### Licence you grant to the Site

For all of your content, you grant us a non-exclusive, worldwide, royalty-free licence to store, reproduce, adapt (for example, to resize and crop pictures and to generate thumbnails and other format variants), publish, and display it, and to cache and distribute it through our infrastructure and content-delivery network — **solely to operate, secure, and promote the Site**. This licence lasts while your content is on the Site and for a reasonable period afterwards for backups.

You also grant every other user the right to view your content on the Site and to quote your comments and forum posts in discussion on the Site.

### Public licence you choose for a picture

When the upload form lets you place a picture under a public licence (such as a [Creative Commons](https://creativecommons.org/) licence) or dedicate it to the public domain, that choice is recorded, shown together with the picture, and allows anyone to reuse the picture on those terms. To the extent the licence you choose is irrevocable, your choice is irrevocable; the operational licence above continues to apply regardless.

If you do not choose a public licence, the picture stays under your copyright and only the operational licence above applies — being on the Site does not put it in the public domain.

### Attribution and provenance

We may display, with each picture, the uploader's username, the author and source information you provided, and any licence that applies. Removing, hiding, or falsifying that information — including watermarks and credits added by the author or rights holder — is a breach of these Terms.

## Moderation, removal, and complaints

Every picture upload is reviewed before it is published. We may remove or restrict any content, and refuse or reverse any catalogue edit, that breaches these Terms, the Rules, or the law, or that does not meet the catalogue's quality requirements.

Where we remove your content or restrict your account, we will tell you the reason and how to appeal, unless the law prevents us from doing so. How this works is described in the Rules and the Privacy Policy.

### Copyright complaints

If you believe content on the Site infringes your copyright, email us at [autowp@gmail.com](mailto:autowp@gmail.com) with the subject line "Copyright complaint" and include:

* identification of the work you say is infringed;
* the address (URL) of the material on the Site;
* your contact details;
* a statement that you believe in good faith that the use is not authorised by the rights holder or the law;
* a statement that the information in your complaint is accurate and that you are the rights holder or authorised to act for them.

We remove or disable access to material that is the subject of a valid complaint, and, where appropriate, we tell the person who posted it, who may ask us to restore it if they believe the complaint is mistaken. We suspend or close the accounts of users who repeatedly infringe.

## How you may not use the Site

You must not:

* download or copy the Site's content in bulk, or use automated means to access it, beyond what a published API or the Site's \`robots.txt\` allows;
* try to break, probe, or circumvent the Site's security, or overload or disrupt the Site or its infrastructure;
* use the Site to distribute spam, malware, or unlawful material;
* impersonate another person or misrepresent your connection with anyone;
* create a new account to get around a suspension or a closed account.

## Our intellectual property

The Site's software, visual design, database structure, and the selection and arrangement of the catalogue are ours or our licensors' and are protected by law. The Site's own source code is published on [GitHub](https://github.com/autowp/autowp) under the licence stated there. Catalogue text and data contributed by users are made available for reuse on the terms shown on the Site. You may link to any page of the Site.

## Disclaimers

The Site and the catalogue are provided "as is" and "as available". The catalogue is a community reference work: we do not warrant that its information is accurate, complete, or up to date, and you should not rely on it for any decision that matters without checking it independently. We do not warrant that the Site will be uninterrupted or error-free.

Nothing in these Terms excludes or limits anything that cannot be excluded or limited under the law that applies to you, including your mandatory rights as a consumer.

## Liability

To the fullest extent permitted by the law that applies to you, we are not liable for:

* indirect or consequential loss;
* loss of data, profit, or goodwill;
* loss arising from your reliance on catalogue information or on content posted by other users.

Nothing here limits our liability for death or personal injury caused by our negligence, for fraud, or for anything else that the law does not allow us to limit.

## Indemnity

If we suffer loss or reasonable costs because your content or your use of the Site broke these Terms or infringed someone's rights, you agree to reimburse us for that loss and those costs. This does not apply to the extent the loss was our fault, and — if you used the Site as a consumer — it applies only where the loss resulted from your breach of these Terms or your negligence.

## Suspension and termination

You may stop using the Site at any time and delete your account from the [account deletion page](/account/delete).

We may suspend or end your access to the Site if you seriously or repeatedly breach these Terms, the Rules, or the law, or where we need to protect the Site or other users. Where it is practical, we will give you notice and a chance to put things right first.

Content you contributed to the catalogue may remain on the Site after your account ends, dissociated from you, as described in the Privacy Policy. Clauses that by their nature should continue after termination — the licences you granted for content still on the Site, your promises about your content, the disclaimers, the liability limits, and the indemnity — continue to apply.

## Changes to these Terms

We may update these Terms. The current version is always available at wheelsage.org/tos and autowp.ru/tos, with the date of the last change shown at the top. If a change is significant, we will make reasonable efforts to notify registered users before it takes effect — for example, by a notice on the Site or by asking you to review the Terms the next time you sign in. If you keep using the Site after a change takes effect, you accept the updated Terms.

## Governing law and disputes

These Terms are governed by the mandatory consumer-protection and other non-waivable laws of the country where you live, and otherwise by the law of the place from which the Site is operated.

If something goes wrong, please contact us first through the [feedback form](/feedback) — most issues are resolved quickly that way. Nothing in these Terms takes away your right to bring proceedings in the courts of your own country where the law gives you that right. If you live in the EU, you can also use the European Commission's [online dispute resolution platform](https://ec.europa.eu/consumers/odr).

## General

If any part of these Terms is found to be unenforceable, the rest continues to apply. If we do not enforce a term, that is not a waiver of it. You may not transfer your rights or obligations under these Terms; we may transfer ours to a successor operator of the Site, on notice, provided your rights are not reduced.

These Terms, together with the Privacy Policy and the Rules, are the whole agreement between you and us about your use of the Site. The English version of these Terms governs; translations are provided for convenience.

## Contact

Questions about these Terms: [autowp@gmail.com](mailto:autowp@gmail.com).
`;

@Component({
  selector: 'app-terms',
  imports: [RouterLink, RemarkComponent],
  templateUrl: './terms.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TermsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly termsText = termsText;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}
