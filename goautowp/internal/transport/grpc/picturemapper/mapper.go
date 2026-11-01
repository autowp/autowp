package picturemapper

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/schema"
)

func ItemsOrderFromProto(order pb.PictureItemsRequest_Order) pictures.PictureItemOrderBy {
	switch order {
	case pb.PictureItemsRequest_NONE:
		return pictures.PictureItemOrderByNone
	case pb.PictureItemsRequest_FRONT_PERSPECTIVES_FIRST:
		return pictures.PictureItemOrderByFrontPerspectivesFirst
	}

	return pictures.PictureItemOrderByNone
}

func StatusFromProto(status pb.PictureStatus) schema.PictureStatus {
	switch status {
	case pb.PictureStatus_PICTURE_STATUS_UNKNOWN:
		return ""
	case pb.PictureStatus_PICTURE_STATUS_ACCEPTED:
		return schema.PictureStatusAccepted
	case pb.PictureStatus_PICTURE_STATUS_REMOVING:
		return schema.PictureStatusRemoving
	case pb.PictureStatus_PICTURE_STATUS_REMOVED:
		return schema.PictureStatusRemoved
	case pb.PictureStatus_PICTURE_STATUS_INBOX:
		return schema.PictureStatusInbox
	}

	return ""
}

func OrderFromProto(order pb.PicturesRequest_Order) pictures.OrderBy {
	switch order {
	case pb.PicturesRequest_ORDER_NONE:
		return pictures.OrderByNone
	case pb.PicturesRequest_ORDER_CREATED_AT_DESC:
		return pictures.OrderByCreatedAtDesc
	case pb.PicturesRequest_ORDER_CREATED_AT_ASC:
		return pictures.OrderByCreatedAtAsc
	case pb.PicturesRequest_ORDER_RESOLUTION_DESC:
		return pictures.OrderByResolutionDesc
	case pb.PicturesRequest_ORDER_RESOLUTION_ASC:
		return pictures.OrderByResolutionAsc
	case pb.PicturesRequest_ORDER_FILESIZE_DESC:
		return pictures.OrderByFilesizeDesc
	case pb.PicturesRequest_ORDER_FILESIZE_ASC:
		return pictures.OrderByFilesizeAsc
	case pb.PicturesRequest_ORDER_COMMENTS:
		return pictures.OrderByComments
	case pb.PicturesRequest_ORDER_VIEWS:
		return pictures.OrderByViews
	case pb.PicturesRequest_ORDER_MODER_VOTES:
		return pictures.OrderByModerVotes
	case pb.PicturesRequest_ORDER_REMOVING_DATE:
		return pictures.OrderByRemovingDate
	case pb.PicturesRequest_ORDER_LIKES:
		return pictures.OrderByLikes
	case pb.PicturesRequest_ORDER_DISLIKES:
		return pictures.OrderByDislikes
	case pb.PicturesRequest_ORDER_STATUS:
		return pictures.OrderByStatus
	case pb.PicturesRequest_ORDER_ACCEPT_DATETIME_DESC:
		return pictures.OrderByAcceptDatetimeDesc
	case pb.PicturesRequest_ORDER_PERSPECTIVES:
		return pictures.OrderByPerspectives
	case pb.PicturesRequest_ORDER_DF_DISTANCE_SIMILARITY:
		return pictures.OrderByDfDistanceSimilarity
	case pb.PicturesRequest_ORDER_FRONT_PERSPECTIVES:
		return pictures.OrderByFrontPerspectives
	}

	return pictures.OrderByNone
}

func LicenseToProto(license schema.PictureLicense) pb.PictureLicense {
	switch license {
	case schema.PictureLicenseUnknown:
		return pb.PictureLicense_PICTURE_LICENSE_UNKNOWN
	case schema.PictureLicenseAllRightsReserved:
		return pb.PictureLicense_PICTURE_LICENSE_ALL_RIGHTS_RESERVED
	case schema.PictureLicenseCC0:
		return pb.PictureLicense_PICTURE_LICENSE_CC0
	case schema.PictureLicenseCCBY:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY
	case schema.PictureLicenseCCBYSA:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY_SA
	case schema.PictureLicenseCCBYNC:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC
	case schema.PictureLicenseCCBYNCSA:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC_SA
	case schema.PictureLicenseCCBYND:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY_ND
	case schema.PictureLicenseCCBYNCND:
		return pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC_ND
	case schema.PictureLicensePublicDomain:
		return pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN
	}

	return pb.PictureLicense_PICTURE_LICENSE_UNKNOWN
}

func ItemTypeToProto(pictureItemType schema.PictureItemType) pb.PictureItemType {
	switch pictureItemType {
	case 0:
		return pb.PictureItemType_PICTURE_ITEM_UNKNOWN
	case schema.PictureItemTypeContent:
		return pb.PictureItemType_PICTURE_ITEM_CONTENT
	case schema.PictureItemTypeAuthor:
		return pb.PictureItemType_PICTURE_ITEM_AUTHOR
	case schema.PictureItemTypeCopyrights:
		return pb.PictureItemType_PICTURE_ITEM_COPYRIGHTS
	}

	return pb.PictureItemType_PICTURE_ITEM_UNKNOWN
}

func ModerVoteTemplateToProto(tpl *schema.PictureModerVoteTemplateRow) *pb.ModerVoteTemplate {
	return &pb.ModerVoteTemplate{
		Id:      tpl.ID,
		UserId:  tpl.UserID,
		Message: tpl.Message,
		Vote:    int32(tpl.Vote),
	}
}

func FieldsFromProto(fields *pb.PictureFields) *pictures.PictureFields {
	if fields == nil {
		return nil
	}

	return &pictures.PictureFields{
		NameText: fields.GetNameText(),
	}
}

func LicenseFromProto(license pb.PictureLicense) schema.PictureLicense {
	switch license {
	case pb.PictureLicense_PICTURE_LICENSE_UNKNOWN:
		return schema.PictureLicenseUnknown
	case pb.PictureLicense_PICTURE_LICENSE_ALL_RIGHTS_RESERVED:
		return schema.PictureLicenseAllRightsReserved
	case pb.PictureLicense_PICTURE_LICENSE_CC0:
		return schema.PictureLicenseCC0
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY:
		return schema.PictureLicenseCCBY
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY_SA:
		return schema.PictureLicenseCCBYSA
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC:
		return schema.PictureLicenseCCBYNC
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC_SA:
		return schema.PictureLicenseCCBYNCSA
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY_ND:
		return schema.PictureLicenseCCBYND
	case pb.PictureLicense_PICTURE_LICENSE_CC_BY_NC_ND:
		return schema.PictureLicenseCCBYNCND
	case pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN:
		return schema.PictureLicensePublicDomain
	}

	return schema.PictureLicenseUnknown
}

func StatusToProto(status schema.PictureStatus) pb.PictureStatus {
	switch status {
	case schema.PictureStatusUnknown:
		return pb.PictureStatus_PICTURE_STATUS_UNKNOWN
	case schema.PictureStatusAccepted:
		return pb.PictureStatus_PICTURE_STATUS_ACCEPTED
	case schema.PictureStatusRemoving:
		return pb.PictureStatus_PICTURE_STATUS_REMOVING
	case schema.PictureStatusInbox:
		return pb.PictureStatus_PICTURE_STATUS_INBOX
	case schema.PictureStatusRemoved:
		return pb.PictureStatus_PICTURE_STATUS_REMOVED
	}

	return pb.PictureStatus_PICTURE_STATUS_UNKNOWN
}

func ItemTypeFromProto(pictureItemType pb.PictureItemType) schema.PictureItemType {
	switch pictureItemType {
	case pb.PictureItemType_PICTURE_ITEM_UNKNOWN:
		return 0
	case pb.PictureItemType_PICTURE_ITEM_CONTENT:
		return schema.PictureItemTypeContent
	case pb.PictureItemType_PICTURE_ITEM_AUTHOR:
		return schema.PictureItemTypeAuthor
	case pb.PictureItemType_PICTURE_ITEM_COPYRIGHTS:
		return schema.PictureItemTypeCopyrights
	}

	return 0
}
