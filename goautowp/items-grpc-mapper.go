package goautowp

import (
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

func convertChildsCounts(childCounts []items.ChildCount) []*ChildsCount {
	result := make([]*ChildsCount, 0, len(childCounts))

	for _, childCount := range childCounts {
		result = append(result, &ChildsCount{
			Type:  extractItemParentType(childCount.Type),
			Count: childCount.Count,
		})
	}

	return result
}

func extractItemTypeID(itemTypeID schema.ItemTableItemTypeID) ItemType {
	switch itemTypeID {
	case schema.ItemTableItemTypeIDVehicle:
		return ItemType_ITEM_TYPE_VEHICLE
	case schema.ItemTableItemTypeIDEngine:
		return ItemType_ITEM_TYPE_ENGINE
	case schema.ItemTableItemTypeIDCategory:
		return ItemType_ITEM_TYPE_CATEGORY
	case schema.ItemTableItemTypeIDTwins:
		return ItemType_ITEM_TYPE_TWINS
	case schema.ItemTableItemTypeIDBrand:
		return ItemType_ITEM_TYPE_BRAND
	case schema.ItemTableItemTypeIDFactory:
		return ItemType_ITEM_TYPE_FACTORY
	case schema.ItemTableItemTypeIDMuseum:
		return ItemType_ITEM_TYPE_MUSEUM
	case schema.ItemTableItemTypeIDPerson:
		return ItemType_ITEM_TYPE_PERSON
	case schema.ItemTableItemTypeIDCopyright:
		return ItemType_ITEM_TYPE_COPYRIGHT
	}

	return ItemType_ITEM_TYPE_UNKNOWN
}

func convertItemTypeID(itemTypeID ItemType) schema.ItemTableItemTypeID {
	switch itemTypeID {
	case ItemType_ITEM_TYPE_UNKNOWN:
		return 0
	case ItemType_ITEM_TYPE_VEHICLE:
		return schema.ItemTableItemTypeIDVehicle
	case ItemType_ITEM_TYPE_ENGINE:
		return schema.ItemTableItemTypeIDEngine
	case ItemType_ITEM_TYPE_CATEGORY:
		return schema.ItemTableItemTypeIDCategory
	case ItemType_ITEM_TYPE_TWINS:
		return schema.ItemTableItemTypeIDTwins
	case ItemType_ITEM_TYPE_BRAND:
		return schema.ItemTableItemTypeIDBrand
	case ItemType_ITEM_TYPE_FACTORY:
		return schema.ItemTableItemTypeIDFactory
	case ItemType_ITEM_TYPE_MUSEUM:
		return schema.ItemTableItemTypeIDMuseum
	case ItemType_ITEM_TYPE_PERSON:
		return schema.ItemTableItemTypeIDPerson
	case ItemType_ITEM_TYPE_COPYRIGHT:
		return schema.ItemTableItemTypeIDCopyright
	}

	return 0
}

func extractItemParentType(itemParentType schema.ItemParentType) ItemParentType {
	switch itemParentType {
	case schema.ItemParentTypeDefault:
		return ItemParentType_ITEM_TYPE_DEFAULT
	case schema.ItemParentTypeTuning:
		return ItemParentType_ITEM_TYPE_TUNING
	case schema.ItemParentTypeSport:
		return ItemParentType_ITEM_TYPE_SPORT
	case schema.ItemParentTypeDesign:
		return ItemParentType_ITEM_TYPE_DESIGN
	}

	return ItemParentType_ITEM_TYPE_DEFAULT
}

func convertItemParentType(itemParentType ItemParentType) schema.ItemParentType {
	switch itemParentType {
	case ItemParentType_ITEM_TYPE_DEFAULT:
		return schema.ItemParentTypeDefault
	case ItemParentType_ITEM_TYPE_TUNING:
		return schema.ItemParentTypeTuning
	case ItemParentType_ITEM_TYPE_SPORT:
		return schema.ItemParentTypeSport
	case ItemParentType_ITEM_TYPE_DESIGN:
		return schema.ItemParentTypeDesign
	}

	return schema.ItemParentTypeDefault
}

func convertItemParentListOptions(in *ItemParentListOptions) (*query.ItemParentListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	result := query.ItemParentListOptions{
		ParentID:   in.GetParentId(),
		ItemID:     in.GetItemId(),
		Type:       convertItemParentType(in.GetType()),
		StrictType: in.GetStrictType(),
		Catname:    in.GetCatname(),
	}

	var err error

	result.ParentItems, err = convertItemListOptions(in.GetParent())
	if err != nil {
		return nil, err
	}

	result.ItemParentParentByChildID, err = convertItemParentListOptions(
		in.GetItemParentParentByChild(),
	)
	if err != nil {
		return nil, err
	}

	result.ChildItems, err = convertItemListOptions(in.GetItem())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheDescendantByChildID, err = convertItemParentCacheListOptions(
		in.GetItemParentCacheItemByChild(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func convertItemParentCacheListOptions(
	in *ItemParentCacheListOptions,
) (*query.ItemParentCacheListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := query.ItemParentCacheListOptions{
		ItemID:                  in.GetItemId(),
		ParentID:                in.GetParentId(),
		ItemVehicleTypeByItemID: convertItemVehicleTypeListOptions(in.GetItemVehicleTypeByItemId()),
	}

	result.ItemsByItemID, err = convertItemListOptions(in.GetItemsByItemId())
	if err != nil {
		return nil, err
	}

	result.ItemsByParentID, err = convertItemListOptions(in.GetItemsByParentId())
	if err != nil {
		return nil, err
	}

	result.PictureItemsByItemID, err = convertPictureItemListOptions(in.GetPictureItemsByItemId())
	if err != nil {
		return nil, err
	}

	result.PictureItemsByParentID, err = convertPictureItemListOptions(
		in.GetPictureItemsByParentId(),
	)
	if err != nil {
		return nil, err
	}

	result.ItemParentByItemID, err = convertItemParentListOptions(in.GetItemParentByItemId())
	if err != nil {
		return nil, err
	}

	result.ItemParentCacheAncestorByItemID, err = convertItemParentCacheListOptions(
		in.GetItemParentCacheAncestorByItemId(),
	)
	if err != nil {
		return nil, err
	}

	return &result, nil
}

func convertLinkListOptions(in *ItemLinkListOptions) (*query.LinkListOptions, error) {
	if in == nil {
		return nil, nil //nolint: nilnil
	}

	var err error

	result := &query.LinkListOptions{
		ID:     in.GetId(),
		ItemID: in.GetItemId(),
		Type:   in.GetType(),
	}

	result.ItemParentCacheDescendant, err = convertItemParentCacheListOptions(
		in.GetItemParentCacheDescendant(),
	)
	if err != nil {
		return nil, err
	}

	return result, nil
}

func convertItemListOptions(in *ItemListOptions) (*query.ItemListOptions, error) {
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
		ItemVehicleType:       convertItemVehicleTypeListOptions(in.GetItemVehicleType()),
	}

	result.ItemParentCacheAncestor, err = convertItemParentCacheListOptions(in.GetAncestor())
	if err != nil {
		return nil, err
	}

	itemTypeID := convertItemTypeID(in.GetTypeId())
	if itemTypeID != 0 {
		result.TypeID = []schema.ItemTableItemTypeID{itemTypeID}
	}

	typeIDs := in.GetTypeIds()
	if len(in.GetTypeIds()) > 0 {
		ids := make([]schema.ItemTableItemTypeID, 0, len(typeIDs))
		for _, id := range in.GetTypeIds() {
			ids = append(ids, convertItemTypeID(id))
		}

		result.TypeID = ids
	}

	result.ItemParentCacheDescendant, err = convertItemParentCacheListOptions(in.GetDescendant())
	if err != nil {
		return nil, err
	}

	result.ItemParentParent, err = convertItemParentListOptions(in.GetParent())
	if err != nil {
		return nil, err
	}

	result.ItemParentChild, err = convertItemParentListOptions(in.GetChild())
	if err != nil {
		return nil, err
	}

	result.PreviewPictures, err = convertPictureItemListOptions(in.GetPreviewPictures())
	if err != nil {
		return nil, err
	}

	result.PictureItems, err = convertPictureItemListOptions(in.GetPictureItems())
	if err != nil {
		return nil, err
	}

	parentTypesOf := convertItemTypeID(in.GetParentTypesOf())
	if parentTypesOf != 0 {
		result.ParentTypesOf = parentTypesOf
	}

	return &result, nil
}

func convertItemFields(fields *ItemFields) *items.ItemFields {
	if fields == nil {
		return nil
	}

	return &items.ItemFields{
		NameOnly:                   fields.GetNameOnly(),
		NameHTML:                   fields.GetNameHtml(),
		NameText:                   fields.GetNameText(),
		NameDefault:                fields.GetNameDefault(),
		Description:                fields.GetDescription(),
		FullText:                   fields.GetFullText(),
		HasText:                    fields.GetHasText(),
		DescendantsCount:           fields.GetDescendantsCount(),
		DescendantPicturesCount:    fields.GetDescendantPicturesCount(),
		ChildsCount:                fields.GetChildsCount(),
		ParentsCount:               fields.GetParentsCount(),
		DescendantTwinsGroupsCount: fields.GetDescendantTwinsGroupsCount(),
		InboxPicturesCount:         fields.GetInboxPicturesCount(),
		AcceptedPicturesCount:      fields.GetAcceptedPicturesCount(),
		ExactPicturesCount:         fields.GetExactPicturesCount(),
		FullName:                   fields.GetFullName(),
		Logo:                       fields.GetLogo120(),
		MostsActive:                fields.GetMostsActive(),
		CommentsAttentionsCount:    fields.GetCommentsAttentionsCount(),
		HasChildSpecs:              fields.GetHasChildSpecs(),
		HasSpecs:                   fields.GetHasSpecs() || fields.GetSpecsRoute(),
		Meta:                       fields.GetMeta(),
	}
}

func convertItemParentFields(_ *ItemParentFields) items.ItemParentFields {
	return items.ItemParentFields{}
}

func convertItemOrder(value ItemsRequest_Order) (items.OrderBy, bool) {
	switch value {
	case ItemsRequest_NAME_NAT:
		return items.OrderByNone, true
	case ItemsRequest_NAME, ItemsRequest_DEFAULT:
		return items.OrderByName, false
	case ItemsRequest_CHILDS_COUNT:
		return items.OrderByChildsCount, false
	case ItemsRequest_AGE:
		return items.OrderByAge, false
	case ItemsRequest_ID_DESC:
		return items.OrderByIDDesc, false
	case ItemsRequest_ID_ASC:
		return items.OrderByIDAsc, false
	}

	return items.OrderByNone, false
}

func convertItemParentOrder(order ItemParentsRequest_Order) items.ItemParentOrderBy {
	switch order {
	case ItemParentsRequest_NONE:
		return items.ItemParentOrderByNone
	case ItemParentsRequest_CATEGORIES_FIRST:
		return items.ItemParentOrderByCategoriesFirst
	case ItemParentsRequest_AUTO:
		return items.ItemParentOrderByAuto
	}

	return items.ItemParentOrderByNone
}
