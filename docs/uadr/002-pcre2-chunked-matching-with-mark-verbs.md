# µADR-002: PCRE2 Chunked Matching with MARK Verbs
-----
tags: #pcre2 #chunking #mark-verbs #regex #performance
status: accepted
context: Invoking individual `preg_match()` calls per dynamic route creates massive Zend VM call-stack overhead and context-switching penalties into PCRE2.
decision:
  - Group dynamic routes into discrete chunks (30 to 50 routes per regex pattern).
  - Combine patterns using regex alternations (`|`) and append `(*MARK:index)` control verbs to identify the matched route branch in a single execution.
consequences:
  - Reduces $N$ regex calls down to a single `preg_match()` execution per bucket chunk.
  - Dramatically improves dynamic route dispatching throughput.
