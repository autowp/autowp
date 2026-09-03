package goautowp

import (
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

func extractPictureModerVoteTemplate(tpl *schema.PictureModerVoteTemplateRow) *ModerVoteTemplate {
	return &ModerVoteTemplate{
		Id:      tpl.ID,
		UserId:  tpl.UserID,
		Message: tpl.Message,
		Vote:    int32(tpl.Vote),
	}
}

func extractPictureItemType(pictureItemType schema.PictureItemType) PictureItemType {
	switch pictureItemType {
	case 0:
		return PictureItemType_PICTURE_ITEM_UNKNOWN
	case schema.PictureItemTypeContent:
		return PictureItemType_PICTURE_ITEM_CONTENT
	case schema.PictureItemTypeAuthor:
		return PictureItemType_PICTURE_ITEM_AUTHOR
	case schema.PictureItemTypeCopyrights:
		return PictureItemType_PICTURE_ITEM_COPYRIGHTS
	}

	return PictureItemType_PICTURE_ITEM_UNKNOWN
}

func convertPictureItemType(pictureItemType PictureItemType) schema.PictureItemType {
	switch pictureItemType {
	case PictureItemType_PICTURE_ITEM_UNKNOWN:
		return 0
	case PictureItemType_PICTURE_ITEM_CONTENT:
		return schema.PictureItemTypeContent
	case PictureItemType_PICTURE_ITEM_AUTHOR:
		return schema.PictureItemTypeAuthor
	case PictureItemType_PICTURE_ITEM_COPYRIGHTS:
		return schema.PictureItemTypeCopyrights
	}

	return 0
}

func extractPictureLicense(license schema.PictureLicense) PictureLicense {
	switch license {
	case schema.PictureLicenseUnknown:
		return PictureLicense_PICTURE_LICENSE_UNKNOWN
	case schema.PictureLicenseAllRightsReserved:
		return PictureLicense_PICTURE_LICENSE_ALL_RIGHTS_RESERVED
	case schema.PictureLicenseCC0:
		return PictureLicense_PICTURE_LICENSE_CC0
	case schema.PictureLicenseCCBY:
		return PictureLicense_PICTURE_LICENSE_CC_BY
	case schema.PictureLicenseCCBYSA:
		return PictureLicense_PICTURE_LICENSE_CC_BY_SA
	case schema.PictureLicenseCCBYNC:
		return PictureLicense_PICTURE_LICENSE_CC_BY_NC
	case schema.PictureLicenseCCBYNCSA:
		return PictureLicense_PICTURE_LICENSE_CC_BY_NC_SA
	case schema.PictureLicenseCCBYND:
		return PictureLicense_PICTURE_LICENSE_CC_BY_ND
	case schema.PictureLicenseCCBYNCND:
		return PictureLicense_PICTURE_LICENSE_CC_BY_NC_ND
	case schema.PictureLicensePublicDomain:
		return PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN
	}

	return PictureLicense_PICTURE_LICENSE_UNKNOWN
}

func convertPictureLicense(license PictureLicense) schema.PictureLicense {
	switch license {
	case PictureLicense_PICTURE_LICENSE_UNKNOWN:
		return schema.PictureLicenseUnknown
	case PictureLicense_PICTURE_LICENSE_ALL_RIGHTS_RESERVED:
		return schema.PictureLicenseAllRightsReserved
	case PictureLicense_PICTURE_LICENSE_CC0:
		return schema.PictureLicenseCC0
	case PictureLicense_PICTURE_LICENSE_CC_BY:
		return schema.PictureLicenseCCBY
	case PictureLicense_PICTURE_LICENSE_CC_BY_SA:
		return schema.PictureLicenseCCBYSA
	case PictureLicense_PICTURE_LICENSE_CC_BY_NC:
		return schema.PictureLicenseCCBYNC
	case PictureLicense_PICTURE_LICENSE_CC_BY_NC_SA:
		return schema.PictureLicenseCCBYNCSA
	case PictureLicense_PICTURE_LICENSE_CC_BY_ND:
		return schema.PictureLicenseCCBYND
	case PictureLicense_PICTURE_LICENSE_CC_BY_NC_ND:
		return schema.PictureLicenseCCBYNCND
	case PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN:
		return schema.PictureLicensePublicDomain
	}

	return schema.PictureLicenseUnknown
}

func convertPictureStatus(status PictureStatus) schema.PictureStatus {
	switch status {
	case PictureStatus_PICTURE_STATUS_UNKNOWN:
		return ""
	case PictureStatus_PICTURE_STATUS_ACCEPTED:
		return schema.PictureStatusAccepted
	case PictureStatus_PICTURE_STATUS_REMOVING:
		return schema.PictureStatusRemoving
	case PictureStatus_PICTURE_STATUS_REMOVED:
		return schema.PictureStatusRemoved
	case PictureStatus_PICTURE_STATUS_INBOX:
		return schema.PictureStatusInbox
	}

	return ""
}

func convertPictureItemListOptions(
	in *PictureItemListOptions,
) (*query.PictureItemListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.PictureItemListOptions{
		PictureID:               in.GetPictureId(),
		ItemID:                  in.GetItemId(),
		TypeID:                  convertPictureItemType(in.GetTypeId()),
		PerspectiveID:           in.GetPerspectiveId(),
		ExcludePerspectiveID:    in.GetExcludePerspectiveId(),
		ExcludeAncestorOrSelfID: in.GetExcludeAncestorOrSelfId(),
		HasNoPerspectiveID:      in.GetHasNoPerspectiveId(),
		ItemVehicleType:         convertItemVehicleTypeListOptions(in.GetItemVehicleType()),
		HasArea:                 in.GetHasArea(),
	}

	var err error

	result.Item, err = convertItemListOptions(in.GetItem())
	if err != nil {
		return nil, err
	}

	result.Pictures, err = convertPictureListOptions(in.GetPictures())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheAncestor, err = convertItemParentCacheListOptions(
		in.GetItemParentCacheAncestor(),
	)
	if err != nil {
		return nil, err
	}

	result.PictureItemByPictureID, err = convertPictureItemListOptions(
		in.GetPictureItemByPictureId(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func convertItemVehicleTypeListOptions(
	in *ItemVehicleTypeListOptions,
) *query.ItemVehicleTypeListOptions {
	if in == nil {
		return nil
	}

	result := query.ItemVehicleTypeListOptions{
		VehicleTypeID: in.GetVehicleTypeId(),
	}

	return &result
}

func convertPictureListOptions(in *PictureListOptions) (*query.PictureListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.PictureListOptions{
		ID:                    in.GetId(),
		IDs:                   in.GetIds(),
		Status:                convertPictureStatus(in.GetStatus()),
		AcceptedInDays:        in.GetAcceptedInDays(),
		OwnerID:               in.GetOwnerId(),
		Identity:              in.GetIdentity(),
		HasNoComments:         in.GetHasNoComments(),
		HasPoint:              in.GetHasPoint(),
		HasNoPoint:            in.GetHasNoPoint(),
		HasNoPictureItem:      in.GetHasNoPictureItem(),
		HasNoReplacePicture:   in.GetHasNoReplacePicture(),
		HasNoPictureModerVote: in.GetHasNoPictureModerVote(),
		CommentTopic:          convertCommentTopicListOptions(in.GetCommentTopic()),
		PictureModerVote:      convertPictureModerVoteListOptions(in.GetPictureModerVote()),
		HasSpecialName:        in.GetHasSpecialName(),
		HasCopyrights:         in.GetHasCopyrights(),
		HasNoCopyrights:       in.GetHasNoCopyrights(),
	}

	var err error

	result.DfDistance, err = convertDfDistanceListOptions(in.GetDfDistance())
	if err != nil {
		return nil, err
	}

	inStatuses := in.GetStatuses()
	if len(inStatuses) > 0 {
		statuses := make([]schema.PictureStatus, 0, len(inStatuses))
		for _, status := range inStatuses {
			statuses = append(statuses, convertPictureStatus(status))
		}

		result.Statuses = statuses
	}

	createDate := in.GetCreateDate()
	if createDate != nil {
		result.CreatedAt = &civil.Date{
			Year:  int(createDate.GetYear()),
			Month: time.Month(createDate.GetMonth()),
			Day:   int(createDate.GetDay()),
		}
	}

	acceptDate := in.GetAcceptDate()
	if acceptDate != nil {
		result.AcceptDate = &civil.Date{
			Year:  int(acceptDate.GetYear()),
			Month: time.Month(acceptDate.GetMonth()),
			Day:   int(acceptDate.GetDay()),
		}
	}

	addedFrom := in.GetAddedFrom()
	if addedFrom != nil {
		result.AddedFrom = &civil.Date{
			Year:  int(addedFrom.GetYear()),
			Month: time.Month(addedFrom.GetMonth()),
			Day:   int(addedFrom.GetDay()),
		}
	}

	result.PictureItem, err = convertPictureItemListOptions(in.GetPictureItem())
	if err != nil {
		return nil, err
	}

	result.ReplacePicture, err = convertPictureListOptions(in.GetReplacePicture())
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func convertPictureModerVoteListOptions(
	in *PictureModerVoteListOptions,
) *query.PictureModerVoteListOptions {
	if in == nil {
		return nil
	}

	return &query.PictureModerVoteListOptions{
		VoteGtZero:  in.GetVoteGtZero(),
		VoteLteZero: in.GetVoteLteZero(),
	}
}

func convertDfDistanceListOptions(in *DfDistanceListOptions) (*query.DfDistanceListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := query.DfDistanceListOptions{}

	result.DstPicture, err = convertPictureListOptions(in.GetDstPicture())
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func convertCommentTopicListOptions(in *CommentTopicListOptions) *query.CommentTopicListOptions {
	if in == nil {
		return nil
	}

	result := query.CommentTopicListOptions{
		MessagesGtZero: in.GetMessagesGtZero(),
	}

	return &result
}

func convertPictureFields(fields *PictureFields) *pictures.PictureFields {
	if fields == nil {
		return nil
	}

	return &pictures.PictureFields{
		NameText: fields.GetNameText(),
	}
}

func convertPicturesOrder(order PicturesRequest_Order) pictures.OrderBy {
	switch order {
	case PicturesRequest_ORDER_NONE:
		return pictures.OrderByNone
	case PicturesRequest_ORDER_CREATED_AT_DESC:
		return pictures.OrderByCreatedAtDesc
	case PicturesRequest_ORDER_CREATED_AT_ASC:
		return pictures.OrderByCreatedAtAsc
	case PicturesRequest_ORDER_RESOLUTION_DESC:
		return pictures.OrderByResolutionDesc
	case PicturesRequest_ORDER_RESOLUTION_ASC:
		return pictures.OrderByResolutionAsc
	case PicturesRequest_ORDER_FILESIZE_DESC:
		return pictures.OrderByFilesizeDesc
	case PicturesRequest_ORDER_FILESIZE_ASC:
		return pictures.OrderByFilesizeAsc
	case PicturesRequest_ORDER_COMMENTS:
		return pictures.OrderByComments
	case PicturesRequest_ORDER_VIEWS:
		return pictures.OrderByViews
	case PicturesRequest_ORDER_MODER_VOTES:
		return pictures.OrderByModerVotes
	case PicturesRequest_ORDER_REMOVING_DATE:
		return pictures.OrderByRemovingDate
	case PicturesRequest_ORDER_LIKES:
		return pictures.OrderByLikes
	case PicturesRequest_ORDER_DISLIKES:
		return pictures.OrderByDislikes
	case PicturesRequest_ORDER_STATUS:
		return pictures.OrderByStatus
	case PicturesRequest_ORDER_ACCEPT_DATETIME_DESC:
		return pictures.OrderByAcceptDatetimeDesc
	case PicturesRequest_ORDER_PERSPECTIVES:
		return pictures.OrderByPerspectives
	case PicturesRequest_ORDER_DF_DISTANCE_SIMILARITY:
		return pictures.OrderByDfDistanceSimilarity
	case PicturesRequest_ORDER_FRONT_PERSPECTIVES:
		return pictures.OrderByFrontPerspectives
	}

	return pictures.OrderByNone
}

func convertPictureItemsOrder(order PictureItemsRequest_Order) pictures.PictureItemOrderBy {
	switch order {
	case PictureItemsRequest_NONE:
		return pictures.PictureItemOrderByNone
	case PictureItemsRequest_FRONT_PERSPECTIVES_FIRST:
		return pictures.PictureItemOrderByFrontPerspectivesFirst
	}

	return pictures.PictureItemOrderByNone
}
