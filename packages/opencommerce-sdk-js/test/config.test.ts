import { test } from "node:test";
import assert from "node:assert/strict";

import { MCPConfig } from "../src/config.ts";

test("constructor still accepts a fully qualified baseUrl directly", () => {
  const config = new MCPConfig({ baseUrl: "https://api.opencommerce.ir/mcp/v1", token: "agent_token" });

  assert.equal(config.baseUrl, "https://api.opencommerce.ir/mcp/v1");
});

test("forVersion builds the baseUrl from host and version", () => {
  const config = MCPConfig.forVersion({ host: "https://api.opencommerce.ir", version: "v2", token: "agent_token" });

  assert.equal(config.baseUrl, "https://api.opencommerce.ir/mcp/v2");
  assert.equal(config.token, "agent_token");
});

test("forVersion trims a trailing slash from the host", () => {
  const config = MCPConfig.forVersion({ host: "https://api.opencommerce.ir/", version: "v1", token: "agent_token" });

  assert.equal(config.baseUrl, "https://api.opencommerce.ir/mcp/v1");
});

test("forVersion passes through timeout and verifySSL", () => {
  const config = MCPConfig.forVersion({
    host: "https://api.opencommerce.ir",
    version: "v2",
    token: "agent_token",
    timeout: 5,
    verifySSL: false,
  });

  assert.equal(config.timeout, 5);
  assert.equal(config.verifySSL, false);
});

test("defaults match the PHP and Python SDKs", () => {
  const config = new MCPConfig({ baseUrl: "https://api.opencommerce.ir/mcp/v1", token: "agent_token" });

  assert.equal(config.timeout, 30);
  assert.equal(config.verifySSL, true);
});
