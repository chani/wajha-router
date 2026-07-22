# µADR-003: Compile-Time Shorthand and Enum Resolution
-----
tags: #compiler #enums #shorthands #zero-runtime #type-safety
status: accepted
context: Parsing route parameter constraints (such as `{id:int}` or `{status:OrderStatus}`) at runtime introduces string parsing and reflection overhead during HTTP requests.
decision:
  - Translate type shorthands (`:int`, `:uuid`, `:slug`, `:alpha`) into raw PCRE2 pattern constraints strictly during `WajhaCompiler` execution.
  - Reflect string-backed PHP 8.1+ Enums at compile time and map their cases directly into regex alternation groups (`case1|case2`).
consequences:
  - Zero runtime parsing or reflection cost for path constraints and enum validation.
  - Type boundary enforcement occurs directly at the PCRE2 execution layer.
