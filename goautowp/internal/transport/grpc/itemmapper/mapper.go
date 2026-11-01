package itemmapper

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
)

func ParentOrderFromProto(order pb.ItemParentsRequest_Order) items.ItemParentOrderBy {
	switch order {
	case pb.ItemParentsRequest_NONE:
		return items.ItemParentOrderByNone
	case pb.ItemParentsRequest_CATEGORIES_FIRST:
		return items.ItemParentOrderByCategoriesFirst
	case pb.ItemParentsRequest_AUTO:
		return items.ItemParentOrderByAuto
	case pb.ItemParentsRequest_NAME:
		return items.ItemParentOrderByName
	}

	return items.ItemParentOrderByNone
}

func ParentFieldsFromProto(_ *pb.ItemParentFields) items.ItemParentFields {
	return items.ItemParentFields{}
}

func TypeToProto(itemTypeID schema.ItemTableItemTypeID) pb.ItemType {
	switch itemTypeID {
	case schema.ItemTableItemTypeIDVehicle:
		return pb.ItemType_ITEM_TYPE_VEHICLE
	case schema.ItemTableItemTypeIDEngine:
		return pb.ItemType_ITEM_TYPE_ENGINE
	case schema.ItemTableItemTypeIDCategory:
		return pb.ItemType_ITEM_TYPE_CATEGORY
	case schema.ItemTableItemTypeIDTwins:
		return pb.ItemType_ITEM_TYPE_TWINS
	case schema.ItemTableItemTypeIDBrand:
		return pb.ItemType_ITEM_TYPE_BRAND
	case schema.ItemTableItemTypeIDFactory:
		return pb.ItemType_ITEM_TYPE_FACTORY
	case schema.ItemTableItemTypeIDMuseum:
		return pb.ItemType_ITEM_TYPE_MUSEUM
	case schema.ItemTableItemTypeIDPerson:
		return pb.ItemType_ITEM_TYPE_PERSON
	case schema.ItemTableItemTypeIDCopyright:
		return pb.ItemType_ITEM_TYPE_COPYRIGHT
	}

	return pb.ItemType_ITEM_TYPE_UNKNOWN
}

func ChildCountsToProto(childCounts []items.ChildCount) []*pb.ChildsCount {
	result := make([]*pb.ChildsCount, 0, len(childCounts))

	for _, childCount := range childCounts {
		result = append(result, &pb.ChildsCount{
			Type:  ParentTypeToProto(childCount.Type),
			Count: childCount.Count,
		})
	}

	return result
}

func OrderFromProto(value pb.ItemsRequest_Order) (items.OrderBy, bool) {
	switch value {
	case pb.ItemsRequest_NAME_NAT:
		return items.OrderByNone, true
	case pb.ItemsRequest_NAME, pb.ItemsRequest_DEFAULT:
		return items.OrderByName, false
	case pb.ItemsRequest_CHILDS_COUNT:
		return items.OrderByChildsCount, false
	case pb.ItemsRequest_AGE:
		return items.OrderByAge, false
	case pb.ItemsRequest_ID_DESC:
		return items.OrderByIDDesc, false
	case pb.ItemsRequest_ID_ASC:
		return items.OrderByIDAsc, false
	}

	return items.OrderByNone, false
}

func ParentTypeToProto(itemParentType schema.ItemParentType) pb.ItemParentType {
	switch itemParentType {
	case schema.ItemParentTypeDefault:
		return pb.ItemParentType_ITEM_TYPE_DEFAULT
	case schema.ItemParentTypeTuning:
		return pb.ItemParentType_ITEM_TYPE_TUNING
	case schema.ItemParentTypeSport:
		return pb.ItemParentType_ITEM_TYPE_SPORT
	case schema.ItemParentTypeDesign:
		return pb.ItemParentType_ITEM_TYPE_DESIGN
	}

	return pb.ItemParentType_ITEM_TYPE_DEFAULT
}

func FieldsFromProto(fields *pb.ItemFields) *items.ItemFields {
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

func ParentTypeFromProto(itemParentType pb.ItemParentType) schema.ItemParentType {
	switch itemParentType {
	case pb.ItemParentType_ITEM_TYPE_DEFAULT:
		return schema.ItemParentTypeDefault
	case pb.ItemParentType_ITEM_TYPE_TUNING:
		return schema.ItemParentTypeTuning
	case pb.ItemParentType_ITEM_TYPE_SPORT:
		return schema.ItemParentTypeSport
	case pb.ItemParentType_ITEM_TYPE_DESIGN:
		return schema.ItemParentTypeDesign
	}

	return schema.ItemParentTypeDefault
}

func TypeFromProto(itemTypeID pb.ItemType) schema.ItemTableItemTypeID {
	switch itemTypeID {
	case pb.ItemType_ITEM_TYPE_UNKNOWN:
		return 0
	case pb.ItemType_ITEM_TYPE_VEHICLE:
		return schema.ItemTableItemTypeIDVehicle
	case pb.ItemType_ITEM_TYPE_ENGINE:
		return schema.ItemTableItemTypeIDEngine
	case pb.ItemType_ITEM_TYPE_CATEGORY:
		return schema.ItemTableItemTypeIDCategory
	case pb.ItemType_ITEM_TYPE_TWINS:
		return schema.ItemTableItemTypeIDTwins
	case pb.ItemType_ITEM_TYPE_BRAND:
		return schema.ItemTableItemTypeIDBrand
	case pb.ItemType_ITEM_TYPE_FACTORY:
		return schema.ItemTableItemTypeIDFactory
	case pb.ItemType_ITEM_TYPE_MUSEUM:
		return schema.ItemTableItemTypeIDMuseum
	case pb.ItemType_ITEM_TYPE_PERSON:
		return schema.ItemTableItemTypeIDPerson
	case pb.ItemType_ITEM_TYPE_COPYRIGHT:
		return schema.ItemTableItemTypeIDCopyright
	}

	return 0
}
