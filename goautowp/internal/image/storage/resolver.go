package storage

import (
	"context"
	"net/url"

	"github.com/aws/aws-sdk-go-v2/service/s3"
	"github.com/aws/smithy-go"
	smithyauth "github.com/aws/smithy-go/auth"
	transport "github.com/aws/smithy-go/endpoints"
	smithyhttp "github.com/aws/smithy-go/transport/http"
)

type Resolver struct {
	URL *url.URL
}

func (r *Resolver) ResolveEndpoint(_ context.Context, params s3.EndpointParameters) (transport.Endpoint, error) {
	endpointURI := *r.URL
	endpointURI.Path += "/" + *params.Bucket

	var (
		signerProperties smithy.Properties
		properties       smithy.Properties
	)

	smithyhttp.SetDisableDoubleEncoding(&signerProperties, true)
	smithyauth.SetAuthOptions(&properties, []*smithyauth.Option{
		{
			SchemeID:         "aws.auth#sigv4",
			SignerProperties: signerProperties,
		},
	})

	return transport.Endpoint{
		URI:        endpointURI,
		Properties: properties,
	}, nil
}
