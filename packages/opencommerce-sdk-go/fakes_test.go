package opencommerce_test

import (
	"context"

	opencommerce "github.com/opencommerceir/opencommerce-platform/packages/opencommerce-sdk-go"
)

// fakeCall records one request a fakeTransport received — this SDK's
// equivalent of the PHP SDK's own Guzzle MockHandler usage. No test in
// this suite ever touches a real socket.
type fakeCall struct {
	method  string
	url     string
	headers map[string]string
	json    map[string]interface{}
}

type fakeTransport struct {
	status int
	body   map[string]interface{}
	calls  []fakeCall
}

func newFakeTransport(status int, body map[string]interface{}) *fakeTransport {
	return &fakeTransport{status: status, body: body}
}

func (f *fakeTransport) Do(
	_ context.Context,
	method, url string,
	headers map[string]string,
	jsonBody map[string]interface{},
	_ int,
) (opencommerce.TransportResponse, error) {
	f.calls = append(f.calls, fakeCall{method: method, url: url, headers: headers, json: jsonBody})
	return opencommerce.TransportResponse{Status: f.status, Body: f.body}, nil
}
