package goautowp

import (
	"context"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type AttrsGRPCServer struct {
	UnimplementedAttrsServer

	repository *attrs.Repository
	auth       *Auth
}

func NewAttrsGRPCServer(repository *attrs.Repository, auth *Auth) *AttrsGRPCServer {
	return &AttrsGRPCServer{
		repository: repository,
		auth:       auth,
	}
}

func convertNullTypeID(typeID schema.NullAttributeTypeID) AttrAttributeType_ID {
	if !typeID.Valid {
		return AttrAttributeType_UNKNOWN
	}

	return convertTypeID(typeID.AttributeTypeID)
}

func convertTypeID(typeID schema.AttrsAttributeTypeID) AttrAttributeType_ID {
	switch typeID {
	case schema.AttrsAttributeTypeIDUnknown:
		return AttrAttributeType_UNKNOWN
	case schema.AttrsAttributeTypeIDString:
		return AttrAttributeType_STRING
	case schema.AttrsAttributeTypeIDInteger:
		return AttrAttributeType_INTEGER
	case schema.AttrsAttributeTypeIDFloat:
		return AttrAttributeType_FLOAT
	case schema.AttrsAttributeTypeIDText:
		return AttrAttributeType_TEXT
	case schema.AttrsAttributeTypeIDBoolean:
		return AttrAttributeType_BOOLEAN
	case schema.AttrsAttributeTypeIDList:
		return AttrAttributeType_LIST
	case schema.AttrsAttributeTypeIDTree:
		return AttrAttributeType_TREE
	}

	return AttrAttributeType_UNKNOWN
}

func convertAttribute(row *schema.AttrsAttributeRow) *AttrAttribute {
	var parentID int64
	if row.ParentID.Valid {
		parentID = row.ParentID.Int64
	}

	var unitID int64
	if row.UnitID.Valid {
		unitID = row.UnitID.Int64
	}

	var description string
	if row.Description.Valid {
		description = row.Description.String
	}

	var precision int32
	if row.Precision.Valid {
		precision = row.Precision.Int32
	}

	return &AttrAttribute{
		Id:          row.ID,
		Name:        row.Name,
		ParentId:    parentID,
		Description: description,
		TypeId:      convertNullTypeID(row.TypeID),
		UnitId:      unitID,
		IsMultiple:  row.Multiple,
		Precision:   precision,
	}
}

func (s *AttrsGRPCServer) GetAttribute(
	ctx context.Context,
	in *GetAttributeRequest,
) (*AttrAttribute, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	row, err := s.repository.Attribute(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if row == nil {
		return nil, status.Error(codes.NotFound, "not found")
	}

	return convertAttribute(row), nil
}

func (s *AttrsGRPCServer) ListAttributes(
	ctx context.Context, in *ListAttributesRequest,
) (*ListAttributesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.Attributes(
		ctx,
		&query.AttrsListOptions{ZoneID: in.GetZoneId(), ParentID: in.GetParentId()},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrAttribute, len(rows))

	for idx, row := range rows {
		res[idx] = convertAttribute(row)
	}

	return &ListAttributesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetAttributeTypes(
	ctx context.Context, _ *emptypb.Empty,
) (*AttrAttributeTypesResponse, error) {
	rows, err := s.repository.AttributeTypes(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrAttributeType, len(rows))
	for idx, row := range rows {
		res[idx] = &AttrAttributeType{
			Id:   convertTypeID(row.ID),
			Name: row.Name,
		}
	}

	return &AttrAttributeTypesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetListOptions(
	ctx context.Context, in *AttrListOptionsRequest,
) (*AttrListOptionsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ListOptions(ctx, in.GetAttributeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	options := make([]*AttrListOption, len(rows))

	for idx, row := range rows {
		var parentID int64
		if row.ParentID.Valid {
			parentID = row.ParentID.Int64
		}

		options[idx] = &AttrListOption{
			Id:          row.ID,
			Name:        row.Name,
			AttributeId: row.AttributeID,
			ParentId:    parentID,
		}
	}

	return &AttrListOptionsResponse{
		Items: options,
	}, nil
}

func (s *AttrsGRPCServer) GetUnits(
	ctx context.Context,
	_ *emptypb.Empty,
) (*AttrUnitsResponse, error) {
	rows, err := s.repository.Units(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	units := make([]*AttrUnit, len(rows))
	for idx, row := range rows {
		units[idx] = &AttrUnit{
			Id:   row.ID,
			Name: row.Name,
			Abbr: row.Abbr,
		}
	}

	return &AttrUnitsResponse{
		Items: units,
	}, nil
}

func (s *AttrsGRPCServer) GetZoneAttributes(
	ctx context.Context, in *AttrZoneAttributesRequest,
) (*AttrZoneAttributesResponse, error) {
	rows, err := s.repository.ZoneAttributes(ctx, in.GetZoneId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrZoneAttribute, len(rows))
	for idx, row := range rows {
		res[idx] = &AttrZoneAttribute{
			ZoneId:      row.ZoneID,
			AttributeId: row.AttributeID,
		}
	}

	return &AttrZoneAttributesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetZones(
	ctx context.Context,
	_ *emptypb.Empty,
) (*AttrZonesResponse, error) {
	rows, err := s.repository.Zones(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrZone, len(rows))
	for idx, row := range rows {
		res[idx] = &AttrZone{
			Id:   row.ID,
			Name: row.Name,
		}
	}

	return &AttrZonesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetValues(
	ctx context.Context,
	in *AttrValuesRequest,
) (*AttrValuesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	rows, err := s.repository.Values(ctx, query.AttrsValueListOptions{
		ZoneID: in.GetZoneId(),
		ItemID: in.GetItemId(),
	}, attrs.ValuesOrderByNone)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrValue, len(rows))

	for idx, row := range rows {
		value, valueText, err := s.repository.ActualValueText(
			ctx,
			row.AttributeID,
			row.ItemID,
			in.GetLanguage(),
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		extractedValue := extractAttrValue(value)

		res[idx] = &AttrValue{
			AttributeId: row.AttributeID,
			ItemId:      row.ItemID,
			Value:       &extractedValue,
			ValueText:   valueText,
		}
	}

	return &AttrValuesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetUserValues(
	ctx context.Context, in *AttrUserValuesRequest,
) (*AttrUserValuesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	if in.GetItemId() == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied: item_id cannot be nil")
	}

	rows, err := s.repository.UserValueRows(ctx, query.AttrsUserValueListOptions{
		ZoneID:        in.GetZoneId(),
		ItemID:        in.GetItemId(),
		UserID:        in.GetUserId(),
		ExcludeUserID: in.GetExcludeUserId(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrUserValue, len(rows))

	for idx, row := range rows {
		var (
			value     attrs.Value
			valueText string
		)

		if in.GetFields().GetValueText() {
			value, valueText, err = s.repository.UserValueText(
				ctx,
				row.AttributeID,
				row.ItemID,
				row.UserID,
				in.GetLanguage(),
			)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		} else {
			value, err = s.repository.UserValue(ctx, row.AttributeID, row.ItemID, row.UserID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		extractedValue := extractAttrValue(value)

		var updateDate *timestamppb.Timestamp
		if row.UpdateDate.Valid {
			updateDate = timestamppb.New(row.UpdateDate.Time)
		}

		res[idx] = &AttrUserValue{
			AttributeId: row.AttributeID,
			ItemId:      row.ItemID,
			UserId:      row.UserID,
			Value:       &extractedValue,
			ValueText:   valueText,
			UpdateTime:  updateDate,
		}
	}

	return &AttrUserValuesResponse{
		Items: res,
	}, nil
}

func (s *AttrsGRPCServer) GetConflicts(
	ctx context.Context,
	in *AttrConflictsRequest,
) (*AttrConflictsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	const conflictsPerPage = 30

	data, pages, err := s.repository.ValuesPaginated(ctx, query.AttrsValueListOptions{
		Conflict: in.GetFilter() == AttrConflictsRequest_ALL,
		UserValues: &query.AttrsUserValueListOptions{
			WeightLtZero:   in.GetFilter() == AttrConflictsRequest_MINUS_WEIGHT,
			ConflictLtZero: in.GetFilter() == AttrConflictsRequest_I_DISAGREE,
			ConflictGtZero: in.GetFilter() == AttrConflictsRequest_DO_NOT_AGREE_WITH_ME,
		},
	}, attrs.ValuesOrderByUpdateDate, in.GetPage(), conflictsPerPage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*AttrConflict, 0, len(data))

	for _, row := range data {
		uvRows, err := s.repository.UserValueRows(ctx, query.AttrsUserValueListOptions{
			AttributeID: row.AttributeID,
			ItemID:      row.ItemID,
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		conflictValues := make([]*AttrConflictValue, 0, len(uvRows))

		for _, uvRow := range uvRows {
			value, uvText, err := s.repository.UserValueText(
				ctx, uvRow.AttributeID, uvRow.ItemID, uvRow.UserID, in.GetLanguage(),
			)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			conflictValues = append(conflictValues, &AttrConflictValue{
				Value:        uvText,
				ValueIsEmpty: value.IsEmpty,
				UserId:       uvRow.UserID,
			})
		}

		res = append(res, &AttrConflict{
			ItemId:      row.ItemID,
			AttributeId: row.AttributeID,
			Values:      conflictValues,
		})
	}

	return &AttrConflictsResponse{
		Items: res,
		Paginator: &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Previous:         pages.Previous,
			Next:             pages.Next,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
		},
	}, nil
}

func (s *AttrsGRPCServer) DeleteUserValues(
	ctx context.Context, in *DeleteAttrUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.admin is required",
		)
	}

	err = s.repository.DeleteUserValue(ctx, in.GetAttributeId(), in.GetItemId(), in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *AttrsGRPCServer) SetUserValues(
	ctx context.Context,
	in *AttrSetUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	for _, item := range in.GetItems() {
		protoValue := item.GetValue()

		_, err = s.repository.SetUserValue(
			ctx,
			userCtx.UserID,
			item.GetAttributeId(),
			item.GetItemId(),
			attrs.Value{
				Valid:       protoValue.GetValid(),
				FloatValue:  protoValue.GetFloatValue(),
				IntValue:    protoValue.GetIntValue(),
				BoolValue:   protoValue.GetBoolValue(),
				ListValue:   protoValue.GetListValue(),
				StringValue: protoValue.GetStringValue(),
				IsEmpty:     protoValue.GetIsEmpty(),
			},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *AttrsGRPCServer) MoveUserValues(
	ctx context.Context,
	in *MoveAttrUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.admin is required",
		)
	}

	srcItemID := in.GetSrcItemId()
	dstItemID := in.GetDestItemId()

	if srcItemID > 0 && dstItemID > 0 {
		err = s.repository.MoveUserValues(ctx, srcItemID, dstItemID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *AttrsGRPCServer) GetSpecifications(
	ctx context.Context, in *GetSpecificationsRequest,
) (*GetSpecificationsResponse, error) {
	table, err := s.repository.Specifications(ctx, []int64{in.GetItemId()}, 0, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	html, err := table.Render()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &GetSpecificationsResponse{
		Html: html,
	}, nil
}

func (s *AttrsGRPCServer) GetChildSpecifications(
	ctx context.Context, in *GetSpecificationsRequest,
) (*GetSpecificationsResponse, error) {
	table, err := s.repository.ChildSpecifications(ctx, in.GetItemId(), in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	html, err := table.Render()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &GetSpecificationsResponse{
		Html: html,
	}, nil
}

func (s *AttrsGRPCServer) GetChartParameters(
	ctx context.Context,
	_ *emptypb.Empty,
) (*ChartParameters, error) {
	rows, err := s.repository.Attributes(ctx, &query.AttrsListOptions{
		IDs: attrs.ChartParameters,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	params := make([]*ChartParameter, 0, len(rows))
	for _, row := range rows {
		params = append(params, &ChartParameter{
			Name: row.Name,
			Id:   row.ID,
		})
	}

	return &ChartParameters{
		Parameters: params,
	}, nil
}

func (s *AttrsGRPCServer) GetChartData(
	ctx context.Context,
	in *ChartDataRequest,
) (*ChartData, error) {
	datasets, err := s.repository.ChartData(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*ChartDataDataset, 0)

	for _, dataset := range datasets {
		pairs := make(map[int32]*AttrValueValue, len(dataset.Pairs))

		for year, value := range dataset.Pairs {
			extractedValue := extractAttrValue(value)
			pairs[int32(year)] = &extractedValue //nolint: gosec
		}

		result = append(result, &ChartDataDataset{
			Name:   dataset.Title,
			Values: pairs,
		})
	}

	return &ChartData{
		Datasets: result,
	}, nil
}
