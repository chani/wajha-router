# Micro Architecture Decision Records (µADRs)

The following µADRs exists so I can revisit my own thoughts, understand why I optimized something in a specific way, or remember why I intentionally rejected a certain approach during benchmarking.

Since Wajha was built alongside my custom microframework **Safi**, it directly aligns with architectural guardrails defined in Safi's core µADRs (which are not published online yet, so here is a quick summary of the ones we rely on):

* **Safi µADR-003 (Permanent APCu Routing Compilation):** Route arrays are compiled once and stored frozen in APCu memory with TTL=0. Wajha's compiler output is designed to be 100% serializable array data specifically to fit this pattern.
* **Safi µADR-004 (Structural Validation Exceptions Inversion):** Domain validation errors (`InvalidArgumentException`) bubble through the router unhandled so the central MVC kernel can translate them into clean HTTP 400 Bad Request responses.
* **Safi µADR-009 (Secure-by-Default Route Metadata):** Routes are locked by default unless explicitly tagged with `'public' => true`. Wajha preserves and returns raw handler option arrays untouched so security layers can evaluate them.
* **Safi µADR-012 (Lightweight Adapters over Core Bloat):** Bans heavy object structures in the core execution path. Wajha uses fast, native PHP array maps rather than forcing PSR-7/15 object allocations in the hot path.
* **Safi µADR-024 (Attribute-Driven Routing Mechanics):** Promotes declarative `#[Route]` attributes on controller methods. Wajha provides `WajhaAttributeLoader` to extract these via reflection without forcing vendor attribute class imports.

---

## Wajha-Specific µADR Index

* **`001`**: [Static Fast-Path & First-Character Path Bucketing](001-static-fastpath-and-first-char-bucketing.md)
* **`002`**: [PCRE2 Chunked Matching with MARK Verbs](002-pcre2-chunked-matching-with-mark-verbs.md)
* **`003`**: [Compile-Time Shorthand and Enum Resolution](003-compile-time-shorthand-and-enum-resolution.md)
* **`004`**: [Positional Branch-Reset PCRE2 Matching](004-positional-branch-reset-pcre2-matching.md)
* **`005`**: [Inlined Zero-Stack Dispatching](005-inlined-zero-stack-dispatching.md)
* **`006`**: [Compile-Time Optional Path Segment Expansion](006-compile-time-optional-segment-expansion.md)
* **`007`**: [Rejected: Core 3D Host/Domain Routing](007-rejected-core-3d-host-domain-routing.md)
* **`008`**: [Decoupled Standalone URL Generator](008-decoupled-standalone-url-generator.md)
* **`009`**: [Rejected: Compiled Code Generation & PHP File Dumping](009-rejected-code-generation-and-dump-engine.md)
* **`010`**: [Framework-Agnostic Duck-Typed Attribute Extraction](010-framework-agnostic-duck-typed-attribute-extraction.md)
* **`011`**: [Lazy RFC 9110 HEAD Fallback Routing](011-lazy-rfc9110-head-fallback-routing.md)
