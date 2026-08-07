import { test } from "node:test";
import assert from "node:assert/strict";

import { MCPClient } from "../src/client.ts";
import { MCPConfig } from "../src/config.ts";
import { AuthorizationException, NotFoundException, ValidationException } from "../src/exceptions.ts";
import { FakeTransport } from "./fakes.ts";

function client(transport: FakeTransport): MCPClient {
  const config = new MCPConfig({ baseUrl: "https://api.opencommerce.ir/mcp/v1", token: "agent_token" });
  return new MCPClient(config, transport);
}

test("discoverCapabilities reads the v1 envelope shape", async () => {
  const transport = new FakeTransport(200, {
    data: { capabilities: [{ name: "commerce.product.search", description: "Search products." }] },
    meta: {},
  });

  const capabilities = await client(transport).discoverCapabilities();

  assert.equal(capabilities.length, 1);
  assert.equal(capabilities[0].name, "commerce.product.search");
});

test("discoverCapabilities reads the v2 envelope shape", async () => {
  const transport = new FakeTransport(200, {
    capabilities: [{ name: "commerce.product.search", description: "Search products." }],
    metadata: {},
  });

  const capabilities = await client(transport).discoverCapabilities();

  assert.equal(capabilities.length, 1);
  assert.equal(capabilities[0].name, "commerce.product.search");
});

test("discoverCapabilities sends the bearer token header", async () => {
  const transport = new FakeTransport(200, { data: { capabilities: [] } });

  await client(transport).discoverCapabilities();

  assert.equal(transport.calls[0].headers.Authorization, "Bearer agent_token");
  assert.equal(transport.calls[0].method, "GET");
});

test("discoverCapabilities throws the mapped exception on a non-2xx response", async () => {
  const transport = new FakeTransport(403, { error: { code: "FORBIDDEN", message: "no access" } });

  await assert.rejects(() => client(transport).discoverCapabilities(), AuthorizationException);
});

test("execute reads the v1 envelope shape", async () => {
  const transport = new FakeTransport(200, { data: { echo: "hi" }, meta: { capability: "demo.tools.echo" } });

  const result = await client(transport).execute("demo.tools.echo", { message: "hi" });

  assert.deepEqual(result.data, { echo: "hi" });
  assert.deepEqual(result.meta, { capability: "demo.tools.echo" });
});

test("execute reads the v2 envelope shape", async () => {
  const transport = new FakeTransport(200, {
    result: { echo: "hi" },
    metadata: { api_version: "v2", timestamp: "2026-01-01T00:00:00Z" },
  });

  const result = await client(transport).execute("demo.tools.echo", { message: "hi" });

  assert.deepEqual(result.data, { echo: "hi" });
  assert.equal(result.meta.api_version, "v2");
});

test("execute sends the capability name and input as JSON", async () => {
  const transport = new FakeTransport(200, { data: {}, meta: {} });

  await client(transport).execute("commerce.product.search", { query: "laptop" });

  assert.equal(transport.calls[0].method, "POST");
  assert.deepEqual(transport.calls[0].jsonBody, {
    capability: "commerce.product.search",
    input: { query: "laptop" },
  });
});

test("execute defaults input to an empty object when omitted", async () => {
  const transport = new FakeTransport(200, { data: {}, meta: {} });

  await client(transport).execute("demo.time.read");

  assert.deepEqual(transport.calls[0].jsonBody?.input, {});
});

test("execute throws ValidationException on a 422", async () => {
  const transport = new FakeTransport(422, { error: { code: "VALIDATION_ERROR", message: "query is required" } });

  await assert.rejects(() => client(transport).execute("commerce.product.search", {}), ValidationException);
});

test("getCapability returns the matching capability", async () => {
  const transport = new FakeTransport(200, {
    data: {
      capabilities: [
        { name: "commerce.product.search", description: "Search." },
        { name: "commerce.order.place", description: "Place an order." },
      ],
    },
  });

  const capability = await client(transport).getCapability("commerce.order.place");

  assert.equal(capability.name, "commerce.order.place");
});

test("getCapability throws NotFoundException when nothing matches", async () => {
  const transport = new FakeTransport(200, { data: { capabilities: [] } });

  await assert.rejects(() => client(transport).getCapability("commerce.nonexistent.capability"), NotFoundException);
});
