package querymapper

import (
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
)

func PictureItemListOptionsFromProto(
	in *pb.PictureItemListOptions,
) (*query.PictureItemListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.PictureItemListOptions{
		PictureID:               in.GetPictureId(),
		ItemID:                  in.GetItemId(),
		TypeID:                  picturemapper.ItemTypeFromProto(in.GetTypeId()),
		PerspectiveID:           in.GetPerspectiveId(),
		ExcludePerspectiveID:    in.GetExcludePerspectiveId(),
		ExcludeAncestorOrSelfID: in.GetExcludeAncestorOrSelfId(),
		HasNoPerspectiveID:      in.GetHasNoPerspectiveId(),
		ItemVehicleType:         ItemVehicleTypeListOptionsFromProto(in.GetItemVehicleType()),
		HasArea:                 in.GetHasArea(),
	}

	var err error

	result.Item, err = ItemListOptionsFromProto(in.GetItem())
	if err != nil {
		return nil, err
	}

	result.Pictures, err = PictureListOptionsFromProto(in.GetPictures())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheAncestor, err = ItemParentCacheListOptionsFromProto(
		in.GetItemParentCacheAncestor(),
	)
	if err != nil {
		return nil, err
	}

	result.PictureItemByPictureID, err = PictureItemListOptionsFromProto(
		in.GetPictureItemByPictureId(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func LinkListOptionsFromProto(in *pb.ItemLinkListOptions) (*query.LinkListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := &query.LinkListOptions{
		ID:     in.GetId(),
		ItemID: in.GetItemId(),
		Type:   in.GetType(),
	}

	result.ItemParentCacheDescendant, err = ItemParentCacheListOptionsFromProto(
		in.GetItemParentCacheDescendant(),
	)
	if err != nil {
		return nil, err
	}

	return result, nil
}

func ItemParentListOptionsFromProto(in *pb.ItemParentListOptions) (*query.ItemParentListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.ItemParentListOptions{
		ParentID:   in.GetParentId(),
		ItemID:     in.GetItemId(),
		Type:       itemmapper.ParentTypeFromProto(in.GetType()),
		StrictType: in.GetStrictType(),
		Catname:    in.GetCatname(),
	}

	var err error

	result.ParentItems, err = ItemListOptionsFromProto(in.GetParent())
	if err != nil {
		return nil, err
	}

	result.ItemParentParentByChildID, err = ItemParentListOptionsFromProto(
		in.GetItemParentParentByChild(),
	)
	if err != nil {
		return nil, err
	}

	result.ChildItems, err = ItemListOptionsFromProto(in.GetItem())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheDescendantByChildID, err = ItemParentCacheListOptionsFromProto(
		in.GetItemParentCacheItemByChild(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func ItemVehicleTypeListOptionsFromProto(
	in *pb.ItemVehicleTypeListOptions,
) *query.ItemVehicleTypeListOptions {
	if in == nil {
		return nil
	}

	result := query.ItemVehicleTypeListOptions{
		VehicleTypeID: in.GetVehicleTypeId(),
	}

	return &result
}

func PictureListOptionsFromProto(in *pb.PictureListOptions) (*query.PictureListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.PictureListOptions{
		ID:                    in.GetId(),
		IDs:                   in.GetIds(),
		Status:                picturemapper.StatusFromProto(in.GetStatus()),
		AcceptedInDays:        in.GetAcceptedInDays(),
		OwnerID:               in.GetOwnerId(),
		Identity:              in.GetIdentity(),
		HasNoComments:         in.GetHasNoComments(),
		HasPoint:              in.GetHasPoint(),
		HasNoPoint:            in.GetHasNoPoint(),
		HasNoPictureItem:      in.GetHasNoPictureItem(),
		HasNoReplacePicture:   in.GetHasNoReplacePicture(),
		HasNoPictureModerVote: in.GetHasNoPictureModerVote(),
		CommentTopic:          CommentTopicListOptionsFromProto(in.GetCommentTopic()),
		PictureModerVote:      PictureModerVoteListOptionsFromProto(in.GetPictureModerVote()),
		HasSpecialName:        in.GetHasSpecialName(),
		HasCopyrights:         in.GetHasCopyrights(),
		HasNoCopyrights:       in.GetHasNoCopyrights(),
	}

	var err error

	result.DfDistance, err = DfDistanceListOptionsFromProto(in.GetDfDistance())
	if err != nil {
		return nil, err
	}

	inStatuses := in.GetStatuses()
	if len(inStatuses) > 0 {
		statuses := make([]schema.PictureStatus, 0, len(inStatuses))
		for _, status := range inStatuses {
			statuses = append(statuses, picturemapper.StatusFromProto(status))
		}

		result.Statuses = statuses
	}

	inLicenses := in.GetLicenses()
	if len(inLicenses) > 0 {
		licenses := make([]schema.PictureLicense, 0, len(inLicenses))
		for _, license := range inLicenses {
			licenses = append(licenses, picturemapper.LicenseFromProto(license))
		}

		result.Licenses = licenses
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

	result.PictureItem, err = PictureItemListOptionsFromProto(in.GetPictureItem())
	if err != nil {
		return nil, err
	}

	result.ReplacePicture, err = PictureListOptionsFromProto(in.GetReplacePicture())
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func PictureModerVoteListOptionsFromProto(
	in *pb.PictureModerVoteListOptions,
) *query.PictureModerVoteListOptions {
	if in == nil {
		return nil
	}

	return &query.PictureModerVoteListOptions{
		VoteGtZero:  in.GetVoteGtZero(),
		VoteLteZero: in.GetVoteLteZero(),
	}
}

func DfDistanceListOptionsFromProto(in *pb.DfDistanceListOptions) (*query.DfDistanceListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := query.DfDistanceListOptions{}

	result.DstPicture, err = PictureListOptionsFromProto(in.GetDstPicture())
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func CommentTopicListOptionsFromProto(in *pb.CommentTopicListOptions) *query.CommentTopicListOptions {
	if in == nil {
		return nil
	}

	result := query.CommentTopicListOptions{
		MessagesGtZero: in.GetMessagesGtZero(),
	}

	return &result
}

func ItemParentCacheListOptionsFromProto(
	in *pb.ItemParentCacheListOptions,
) (*query.ItemParentCacheListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := query.ItemParentCacheListOptions{
		ItemID:                  in.GetItemId(),
		ParentID:                in.GetParentId(),
		ItemVehicleTypeByItemID: ItemVehicleTypeListOptionsFromProto(in.GetItemVehicleTypeByItemId()),
	}

	result.ItemsByItemID, err = ItemListOptionsFromProto(in.GetItemsByItemId())
	if err != nil {
		return nil, err
	}

	result.ItemsByParentID, err = ItemListOptionsFromProto(in.GetItemsByParentId())
	if err != nil {
		return nil, err
	}

	result.PictureItemsByItemID, err = PictureItemListOptionsFromProto(in.GetPictureItemsByItemId())
	if err != nil {
		return nil, err
	}

	result.PictureItemsByParentID, err = PictureItemListOptionsFromProto(
		in.GetPictureItemsByParentId(),
	)
	if err != nil {
		return nil, err
	}

	result.ItemParentByItemID, err = ItemParentListOptionsFromProto(in.GetItemParentByItemId())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheAncestorByItemID, err = ItemParentCacheListOptionsFromProto(
		in.GetItemParentCacheAncestorByItemId(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func ItemListOptionsFromProto(in *pb.ItemListOptions) (*query.ItemListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := query.ItemListOptions{
		NoParents:             in.GetNoParent(),
		Catname:               in.GetCatname(),
		IsConcept:             in.GetIsConcept(),
		IsNotConcept:          in.GetIsNotConcept(),
		IsNotConceptInherited: in.GetIsNotConceptInherited(),
		Name:                  in.GetName(),
		NameExclude:           in.GetNameExclude(),
		ItemID:                in.GetId(),
		EngineItemID:          in.GetEngineId(),
		IsGroup:               in.GetIsGroup(),
		Autocomplete:          in.GetAutocomplete(),
		SuggestionsTo:         in.GetSuggestionsTo(),
		ExcludeSelfAndChilds:  in.GetExcludeSelfAndChilds(),
		Dateless:              in.GetDateless(),
		Dateful:               in.GetDateful(),
		SpecID:                in.GetSpecId(),
		BeginYear:             in.GetBeginYear(),
		EndYear:               in.GetEndYear(),
		Text:                  in.GetText(),
		NoVehicleType:         in.GetNoVehicleType(),
		ItemVehicleType:       ItemVehicleTypeListOptionsFromProto(in.GetItemVehicleType()),
		NameFirstChar:         in.GetNameFirstChar(),
	}

	result.ItemParentCacheAncestor, err = ItemParentCacheListOptionsFromProto(in.GetAncestor())
	if err != nil {
		return nil, err
	}

	itemTypeID := itemmapper.TypeFromProto(in.GetTypeId())
	if itemTypeID != 0 {
		result.TypeID = []schema.ItemTableItemTypeID{itemTypeID}
	}

	typeIDs := in.GetTypeIds()
	if len(in.GetTypeIds()) > 0 {
		ids := make([]schema.ItemTableItemTypeID, 0, len(typeIDs))
		for _, id := range in.GetTypeIds() {
			ids = append(ids, itemmapper.TypeFromProto(id))
		}

		result.TypeID = ids
	}

	result.ItemParentCacheDescendant, err = ItemParentCacheListOptionsFromProto(in.GetDescendant())
	if err != nil {
		return nil, err
	}

	result.ItemParentParent, err = ItemParentListOptionsFromProto(in.GetParent())
	if err != nil {
		return nil, err
	}

	result.ItemParentChild, err = ItemParentListOptionsFromProto(in.GetChild())
	if err != nil {
		return nil, err
	}

	result.PreviewPictures, err = PictureItemListOptionsFromProto(in.GetPreviewPictures())
	if err != nil {
		return nil, err
	}

	result.PictureItems, err = PictureItemListOptionsFromProto(in.GetPictureItems())
	if err != nil {
		return nil, err
	}

	parentTypesOf := itemmapper.TypeFromProto(in.GetParentTypesOf())
	if parentTypesOf != 0 {
		result.ParentTypesOf = parentTypesOf
	}

	return &result, nil
}
