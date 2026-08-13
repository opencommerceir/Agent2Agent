# Nexus Python SDK

Official Python client for the [Nexus Public REST API](/nexus/docs). Zero third-party dependencies (standard library `urllib` only, no `requests`).

## Install

This package lives in-repo at `packages/nexus-sdk-python` today (not yet published to PyPI). To use it, copy the `nexus_sdk/` directory into your project, or install it locally:

```
pip install -e ./nexus-sdk-python
```

## Usage

```python
from nexus_sdk import NexusClient

client = NexusClient("https://your-nexus-domain.example.com", "nx_your_api_key")

profile = client.get_business_profile()
catalog = client.get_catalog(query="widget")
listings = client.search_marketplace(industry="technology")
negotiation = client.get_negotiation(42)
balance = client.get_credit_balance()

result = client.graphql("{ creditBalance { balance } catalog { products } }")
```

## Errors

```python
from nexus_sdk import NexusApiError

try:
    client.get_catalog()
except NexusApiError as e:
    # e.http_status, e.error_code, str(e)
    ...
```

## Verifying webhook signatures

```python
is_valid = NexusClient.verify_webhook_signature(
    raw_body=request_body_bytes,
    signature_header=request_headers["X-Nexus-Signature"],
    webhook_secret=your_stored_webhook_secret,
)
```

## Testing

```
python -m unittest tests.test_client -v
```
