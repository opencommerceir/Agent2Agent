/**
 * A minimal, standalone AI Agent script — proof that a plain Node.js/
 * TypeScript script can discover and execute OpenCommerce capabilities
 * using nothing but `npm install @opencommerce/sdk`.
 *
 * Prerequisites:
 *   1. `php artisan serve` running this app (default: http://localhost:8000)
 *   2. An Agent token — generate one via GenerateAgentTokenAction, or see
 *      the "Quick Start" section of packages/opencommerce-sdk/README.md
 *      for a copy-pasteable Tinker snippet that creates a Tenant, Org,
 *      Agent, grants the three demo.* permissions, and prints a token.
 *
 * Usage (Node.js 23.6+, which can run TypeScript directly):
 *   node examples/sample-agent.ts <token> [base-url]
 *
 * Outside this monorepo, replace the import below with
 * `import { MCPClient, MCPConfig, MCPException } from "@opencommerce/sdk";`
 * after `npm install @opencommerce/sdk`.
 */

import { MCPClient, MCPConfig, MCPException } from "../packages/opencommerce-sdk-js/src/index.ts";

async function main(): Promise<number> {
  const token = process.argv[2];
  const baseUrl = process.argv[3] ?? "http://localhost:8000/mcp/v1";

  if (!token) {
    console.error("Usage: node examples/sample-agent.ts <token> [base-url]");
    return 1;
  }

  const config = new MCPConfig({ baseUrl, token });
  const client = new MCPClient(config);

  console.log("=== Available Capabilities ===");
  try {
    const capabilities = await client.discoverCapabilities();
    for (const capability of capabilities) {
      console.log(`- ${capability.name}: ${capability.description}`);
    }
  } catch (error) {
    if (error instanceof MCPException) {
      console.error(`Discovery failed: [${error.errorCode}] ${error.message}`);
    }
    return 1;
  }

  console.log("\n=== demo.tools.echo ===");
  try {
    const result = await client.execute("demo.tools.echo", { message: "Hello from AI Agent!" });
    console.log(result.data);
  } catch (error) {
    if (error instanceof MCPException) {
      console.error(`demo.tools.echo failed: [${error.errorCode}] ${error.message}`);
    }
  }

  console.log("\n=== demo.tools.time ===");
  try {
    const result = await client.execute("demo.tools.time");
    console.log(result.data);
  } catch (error) {
    if (error instanceof MCPException) {
      console.error(`demo.tools.time failed: [${error.errorCode}] ${error.message}`);
    }
  }

  console.log("\n=== demo.tools.calculator ===");
  try {
    const result = await client.execute("demo.tools.calculator", { operation: "multiply", a: 42, b: 10 });
    console.log(result.data);
  } catch (error) {
    if (error instanceof MCPException) {
      console.error(`demo.tools.calculator failed: [${error.errorCode}] ${error.message}`);
    }
  }

  console.log("\n=== Negative test: unknown capability ===");
  try {
    // Well-formed (domain.resource.action) but genuinely unregistered —
    // a malformed name like "demo.nonexistent" would fail format
    // validation (VALIDATION_ERROR) before ever reaching the "does this
    // exist" check this test is meant to demonstrate (NOT_FOUND).
    await client.execute("demo.tools.nonexistent", {});
  } catch (error) {
    if (error instanceof MCPException) {
      console.log(`Correctly rejected: [${error.errorCode}] ${error.message}`);
    }
  }

  return 0;
}

main().then((code) => process.exit(code));
