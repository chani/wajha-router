# µADR-006: Compile-Time Optional Path Segment Expansion
-----
tags: #routing #optional-segments #compiler #combinatorics #kiss
status: accepted
context: Evaluating optional URL path segments (e.g., `/archive[/{year}]`) at runtime via complex nested optional non-capturing regex groups degrades PCRE2 evaluation speed.
decision:
  - Expand bracketed optional route patterns recursively into discrete, flat route entries during `WajhaCompiler::addRoute()`.
consequences:
  - Preserves flat $O(1)$ and single-pass PCRE2 matching mechanics at runtime.
  - Slightly increases compiled route array size in exchange for maximum runtime execution speed.
