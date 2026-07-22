# µADR-004: Positional Branch-Reset PCRE2 Matching
-----
tags: #pcre2 #branch-reset #positional-captures #performance #optimization
status: accepted
context: Named capture groups (`(?<var>...)`) inside combined chunk patterns force PCRE2 to construct named match arrays, incurring internal C-level hashtable lookup penalties and allocations in `libpcre2`.
decision:
  - Compile dynamic route chunks using PCRE2 branch-reset groups `(?|...)` to reset capture group index numbering across alternations.
  - Extract route variables positionally via numeric array indexes (`$matches[1]`, `$matches[2]`) in `WajhaDispatcher`.
consequences:
  - Completely eliminates C-level subpattern hashtable allocation overhead in PCRE2.
  - Closes performance gaps and yields higher execution ratios on dynamic routes.
