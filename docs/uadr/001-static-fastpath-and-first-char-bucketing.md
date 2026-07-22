# µADR-001: Static Fast-Path & First-Character Path Bucketing
-----
tags: #routing #fastpath #bucketing #performance #pcre2
status: accepted
context: Evaluating every registered regex pattern on incoming requests causes $O(N)$ runtime degradation as the application route table grows.
decision:
  - Implement a two-tier routing structure separating static and dynamic routes at compile time.
  - Execute direct 2D array lookups (`$staticRoutes[$method][$uri]`) for $O(1)$ fast-path resolution, completely bypassing the PCRE2 engine.
  - Partition dynamic routes into character buckets using the second URI character (`$uri[1]`) to narrow down pattern checks and bypass unrelated regex groups.
consequences:
  - Sub-0.4 microsecond latency for static endpoints.
  - Drastically reduced search space for dynamic routes prior to invoking PCRE2 matching.
