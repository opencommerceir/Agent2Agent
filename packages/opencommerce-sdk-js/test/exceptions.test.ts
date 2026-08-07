import { test } from "node:test";
import assert from "node:assert/strict";

import {
  AuthenticationException,
  AuthorizationException,
  MCPException,
  NotFoundException,
  ValidationException,
  exceptionFromResponse,
} from "../src/exceptions.ts";

test("401 maps to AuthenticationException", () => {
  const exc = exceptionFromResponse(401, { error: { code: "UNAUTHORIZED", message: "no token" } });

  assert.ok(exc instanceof AuthenticationException);
  assert.ok(exc instanceof MCPException);
  assert.equal(exc.errorCode, "UNAUTHORIZED");
  assert.equal(exc.message, "no token");
  assert.equal(exc.statusCode, 401);
});

test("403 maps to AuthorizationException", () => {
  const exc = exceptionFromResponse(403, { error: { code: "FORBIDDEN", message: "missing permission" } });

  assert.ok(exc instanceof AuthorizationException);
});

test("404 maps to NotFoundException", () => {
  const exc = exceptionFromResponse(404, { error: { code: "NOT_FOUND", message: "Order not found" } });

  assert.ok(exc instanceof NotFoundException);
});

test("422 maps to ValidationException", () => {
  const exc = exceptionFromResponse(422, { error: { code: "VALIDATION_ERROR", message: "bad input" } });

  assert.ok(exc instanceof ValidationException);
});

test("an unmapped status falls back to the base MCPException", () => {
  const exc = exceptionFromResponse(500, { error: { code: "INTERNAL_ERROR", message: "boom" } });

  assert.equal(exc.constructor, MCPException);
  assert.equal(exc.statusCode, 500);
});

test("429 also falls back to the base MCPException", () => {
  const exc = exceptionFromResponse(429, { error: { code: "TOO_MANY_REQUESTS", message: "slow down" } });

  assert.equal(exc.constructor, MCPException);
});

test("a missing error envelope gets sensible defaults", () => {
  const exc = exceptionFromResponse(500, {});

  assert.equal(exc.errorCode, "UNKNOWN_ERROR");
  assert.ok(exc.message.includes("500"));
});
